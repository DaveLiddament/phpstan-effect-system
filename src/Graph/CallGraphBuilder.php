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
     * @param list<array{caller: string, line: int, callees: list<array{key: string, calledClass: string|null, method: string|null, dispatch: bool}>}> $callRecords
     */
    public function build(array $callRecords, ClassHierarchy $hierarchy, Declarations $declarations): CallGraph
    {
        $graph = new CallGraph();
        foreach ($callRecords as $record) {
            foreach ($record['callees'] as $callee) {
                // Direct edge to the declared method: carries effects declared
                // on interface/abstract methods themselves.
                $graph->addEdge($record['caller'], $callee['key']);

                if (!$callee['dispatch'] || $callee['calledClass'] === null || $callee['method'] === null) {
                    continue;
                }

                // Closed-world dynamic dispatch: the call may land on any
                // known subtype's implementation.
                $calledClassLower = strtolower(ltrim($callee['calledClass'], '\\'));
                $methodLower = strtolower($callee['method']);
                foreach ($hierarchy->subtypesOf($calledClassLower) as $subtype) {
                    if ($this->isExcluded($subtype)) {
                        continue;
                    }
                    $implementationKey = $hierarchy->resolveImplementation($declarations, $subtype, $methodLower);
                    if ($implementationKey === null) {
                        continue;
                    }
                    $graph->addEdge($record['caller'], $implementationKey);
                }
            }
        }

        return $graph;
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
