<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

use const FNM_CASEFOLD;
use const FNM_NOESCAPE;

final class CallGraphBuilder
{
    /**
     * @param list<string> $excludeImplementationsFrom fnmatch patterns for
     *        implementation class names to skip during dispatch expansion
     *        (test doubles must not pollute the closed-world union)
     */
    public function __construct(
        private array $excludeImplementationsFrom = [],
    ) {
    }

    /**
     * @param list<string> $callRecords encoded by CallRecord
     */
    public function build(array $callRecords, ClassHierarchy $hierarchy, Declarations $declarations): CallGraph
    {
        $graph = new CallGraph();
        $dispatchTargets = [];
        // explode() returns new strings for every record; keep one copy per key.
        $keys = [];
        foreach ($callRecords as $record) {
            $callees = explode("\n", $record);
            $caller = array_shift($callees);
            $caller = $keys[$caller] ??= $caller;
            foreach ($callees as $callee) {
                $parts = explode("\t", $callee);
                // Direct edge to the declared method: carries effects declared
                // on interface/abstract methods themselves.
                $graph->addEdge($caller, $keys[$parts[0]] ??= $parts[0]);

                if (count($parts) !== 3) {
                    continue;
                }

                // Closed-world dynamic dispatch: the call may land on any
                // known subtype's implementation.
                $calledClassLower = strtolower(ltrim($parts[1], '\\'));
                $methodLower = strtolower($parts[2]);
                $targetKey = $calledClassLower . '::' . $methodLower;
                if (!isset($dispatchTargets[$targetKey])) {
                    $dispatchTargets[$targetKey] = true;
                    $graph->addDispatchTarget($targetKey, $this->dispatchTargets($hierarchy, $declarations, $calledClassLower, $methodLower));
                }
                $graph->addDispatchEdge($caller, $targetKey);
            }
        }

        return $graph;
    }

    /**
     * @return list<string>
     */
    private function dispatchTargets(ClassHierarchy $hierarchy, Declarations $declarations, string $calledClassLower, string $methodLower): array
    {
        $implementationKeys = [];
        foreach ($hierarchy->subtypesOf($calledClassLower) as $subtype) {
            if ($this->isExcluded($subtype)) {
                continue;
            }
            $implementationKey = $hierarchy->resolveImplementation($declarations, $subtype, $methodLower);
            if ($implementationKey === null) {
                continue;
            }
            $implementationKeys[] = $implementationKey;
        }

        return $implementationKeys;
    }

    public function isExcluded(string $classLower): bool
    {
        foreach ($this->excludeImplementationsFrom as $pattern) {
            // FNM_NOESCAPE is essential: without it the backslashes in
            // patterns like 'Tests\*' escape the wildcard.
            if (fnmatch($pattern, $classLower, FNM_NOESCAPE | FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
