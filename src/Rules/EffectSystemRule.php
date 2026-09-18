<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Rules;

use DaveLiddament\PhpstanEffectSystem\Collectors\CallCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\ClassHierarchyCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\FunctionCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\FunctionDeclarationCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\MethodCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\MethodDeclarationCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\StaticMethodCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Config\EffectsConfig;
use DaveLiddament\PhpstanEffectSystem\Graph\CallGraph;
use DaveLiddament\PhpstanEffectSystem\Graph\CallGraphBuilder;
use DaveLiddament\PhpstanEffectSystem\Graph\ClassHierarchy;
use DaveLiddament\PhpstanEffectSystem\Graph\ContractOrigin;
use DaveLiddament\PhpstanEffectSystem\Graph\DeclarationRecord;
use DaveLiddament\PhpstanEffectSystem\Graph\Declarations;
use DaveLiddament\PhpstanEffectSystem\Graph\EffectPropagator;
use DaveLiddament\PhpstanEffectSystem\Graph\PathFinder;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Runs once on the collected whole-project data: builds the call graph,
 * computes transitive effect reachability and enforces EffectFree contracts.
 *
 * @implements Rule<CollectedDataNode>
 */
final class EffectSystemRule implements Rule
{
    private EffectsConfig $config;

    /**
     * @param list<array{method?: string, function?: string, effects?: list<string>}> $stubs
     * @param list<array{classPattern?: string, methodPattern?: string, effectFree?: list<string>}> $rules
     * @param list<string> $allowedEffects
     * @param list<string> $excludeImplementationsFrom
     */
    public function __construct(
        array $stubs,
        array $rules,
        array $allowedEffects,
        array $excludeImplementationsFrom,
    ) {
        $this->config = EffectsConfig::fromParameters($stubs, $rules, $allowedEffects, $excludeImplementationsFrom);
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isOnlyFilesAnalysis()) {
            // Closed-world reasoning needs the full project; a partial file set
            // would silently under-report, so report nothing at all.
            return [];
        }

        $declarations = $this->buildDeclarations($node);
        $hierarchy = $this->buildHierarchy($node);
        $graphBuilder = new CallGraphBuilder($this->config->excludeImplementationsFrom);
        $graph = $this->buildGraph($node, $hierarchy, $declarations, $graphBuilder);

        $declared = [];
        $handles = [];
        foreach ($declarations->all() as $key => $record) {
            if ($record->effects !== []) {
                $declared[$key] = array_fill_keys($record->effects, true);
            }
            if ($record->handles === []) {
                continue;
            }
            $handles[$key] = array_fill_keys($record->handles, true);
        }

        $effects = (new EffectPropagator())->propagate($graph, $declared, $handles);
        $contracts = $this->collectContracts($declarations, $hierarchy, $graphBuilder);

        return $this->buildErrors($declarations, $graph, $contracts, $effects, $declared);
    }

    private function buildDeclarations(CollectedDataNode $node): Declarations
    {
        $declarations = new Declarations();
        foreach ([MethodDeclarationCollector::class, FunctionDeclarationCollector::class] as $collectorClass) {
            foreach ($node->get($collectorClass) as $fileRecords) {
                foreach ($fileRecords as $record) {
                    $declarations->add(DeclarationRecord::fromCollectedArray($record));
                }
            }
        }

        // Stubs describe unanalysed vendor code; analysed declarations win on
        // key collisions because they were added first.
        foreach ($this->config->stubRecords as $stubRecord) {
            $declarations->add($stubRecord);
        }

        return $declarations;
    }

    private function buildHierarchy(CollectedDataNode $node): ClassHierarchy
    {
        $records = [];
        foreach ($node->get(ClassHierarchyCollector::class) as $fileRecords) {
            foreach ($fileRecords as $record) {
                $records[] = $record;
            }
        }

        return ClassHierarchy::fromCollectedRecords($records);
    }

    private function buildGraph(CollectedDataNode $node, ClassHierarchy $hierarchy, Declarations $declarations, CallGraphBuilder $graphBuilder): CallGraph
    {
        $callRecords = [];
        foreach ([CallCollector::class, MethodCallableCollector::class, StaticMethodCallableCollector::class, FunctionCallableCollector::class] as $collectorClass) {
            foreach ($node->get($collectorClass) as $fileRecords) {
                foreach ($fileRecords as $record) {
                    $callRecords[] = $record;
                }
            }
        }

        return $graphBuilder->build($callRecords, $hierarchy, $declarations);
    }

    /**
     * Collects EffectFree contracts per graph key. Contracts cannot be dropped
     * by overrides; when the same (method, effect) contract arises repeatedly,
     * the own attribute wins over inherited ones, which win over pattern
     * rules.
     *
     * @return array<string, array<string, ContractOrigin>> key => effect => origin
     */
    private function collectContracts(Declarations $declarations, ClassHierarchy $hierarchy, CallGraphBuilder $graphBuilder): array
    {
        $contracts = [];
        foreach ($declarations->all() as $key => $record) {
            foreach ($record->effectFree as $effect) {
                $contracts[$key][$effect] = ContractOrigin::own();
            }
        }

        foreach ($declarations->all() as $key => $record) {
            if ($record->className === null) {
                continue;
            }
            $classLower = strtolower(ltrim($record->className, '\\'));
            $methodLower = strtolower($record->name);
            foreach ($hierarchy->ancestorsOf($classLower) as $ancestor) {
                $ancestorKey = $declarations->methodKeyOfClass($ancestor, $methodLower);
                if ($ancestorKey === null || $ancestorKey === $key) {
                    continue;
                }
                $ancestorRecord = $declarations->get($ancestorKey);
                if ($ancestorRecord === null || !$ancestorRecord->passesContractToOverrides()) {
                    continue;
                }
                foreach ($ancestorRecord->effectFree as $effect) {
                    if (isset($contracts[$key][$effect])) {
                        // Own contract (or one from a nearer ancestor) wins.
                        continue;
                    }
                    $contracts[$key][$effect] = ContractOrigin::inheritedFrom($ancestorRecord->displayName());
                }
            }
        }

        // A class can fulfil an ancestor's contract with a method it merely
        // inherits (class Child extends Base implements Runner, with run()
        // declared only in Base). Base is unrelated to Runner, so the loop
        // above never pairs them: pair them through each class that is. Test
        // doubles are skipped, so they cannot impose contracts on production
        // code any more than they contribute effects to it.
        foreach ($hierarchy->classNames() as $classLower => $classDisplayName) {
            if ($graphBuilder->isExcluded($classLower)) {
                continue;
            }
            foreach ($hierarchy->ancestorsOf($classLower) as $ancestor) {
                foreach ($declarations->methodKeysOfClass($ancestor) as $methodLower => $ancestorKey) {
                    $ancestorRecord = $declarations->get($ancestorKey);
                    if ($ancestorRecord === null || !$ancestorRecord->passesContractToOverrides() || $ancestorRecord->effectFree === []) {
                        continue;
                    }
                    $implementationKey = $hierarchy->resolveImplementation($declarations, $classLower, $methodLower);
                    if ($implementationKey === null || $implementationKey === $ancestorKey) {
                        continue;
                    }
                    foreach ($ancestorRecord->effectFree as $effect) {
                        if (isset($contracts[$implementationKey][$effect])) {
                            continue;
                        }
                        $contracts[$implementationKey][$effect] = ContractOrigin::inheritedFrom($ancestorRecord->displayName(), $classDisplayName);
                    }
                }
            }
        }

        foreach ($this->config->patternRules as $rule) {
            foreach ($declarations->all() as $key => $record) {
                if ($record->className === null || $record->file === null) {
                    continue;
                }
                if (!fnmatch($rule['classPattern'], $record->className, FNM_NOESCAPE | FNM_CASEFOLD)) {
                    continue;
                }
                if (!fnmatch($rule['methodPattern'], $record->name, FNM_NOESCAPE | FNM_CASEFOLD)) {
                    continue;
                }
                foreach ($rule['effectFree'] as $effect) {
                    if (isset($contracts[$key][$effect])) {
                        continue;
                    }
                    $contracts[$key][$effect] = ContractOrigin::fromPatternRule($rule['classPattern'], $rule['methodPattern']);
                }
            }
        }

        return $contracts;
    }

    /**
     * @param array<string, array<string, ContractOrigin>> $contracts
     * @param array<string, array<string, true>> $effects
     * @param array<string, array<string, true>> $declared
     * @return list<IdentifierRuleError>
     */
    private function buildErrors(Declarations $declarations, CallGraph $graph, array $contracts, array $effects, array $declared): array
    {
        $pathFinder = new PathFinder();

        /** @var list<array{file: string, line: int, message: string, identifier: string}> $errorData */
        $errorData = [];

        if ($this->config->allowedEffects !== []) {
            foreach ($declarations->all() as $record) {
                if ($record->file === null || $record->line === null) {
                    continue;
                }
                foreach (array_unique([...$record->effects, ...$record->effectFree, ...$record->handles]) as $effect) {
                    if (in_array($effect, $this->config->allowedEffects, true)) {
                        continue;
                    }
                    $errorData[] = [
                        'file' => $record->file,
                        'line' => $record->line,
                        'message' => sprintf(
                            "%s %s uses unknown effect '%s'. Allowed effects: %s.",
                            $record->className !== null ? 'Method' : 'Function',
                            $record->displayName(),
                            $effect,
                            implode(', ', $this->config->allowedEffects),
                        ),
                        'identifier' => 'effects.unknownEffect',
                    ];
                }
            }
        }

        foreach ($contracts as $key => $effectOrigins) {
            $record = $declarations->get($key);
            if ($record === null || $record->file === null || $record->line === null) {
                continue;
            }

            foreach ($effectOrigins as $effect => $origin) {
                if (isset($declared[$key][$effect])) {
                    $errorData[] = [
                        'file' => $record->file,
                        'line' => $record->line,
                        'message' => $this->contradictionMessage($record, $effect, $origin),
                        'identifier' => 'effects.contradiction',
                    ];
                    continue;
                }

                if (!isset($effects[$key][$effect])) {
                    continue;
                }

                $path = $pathFinder->findPath($graph, $key, $effect, $effects, $declared);
                $errorData[] = [
                    'file' => $record->file,
                    'line' => $record->line,
                    'message' => $this->violationMessage($declarations, $record, $effect, $origin, $path),
                    'identifier' => 'effects.violation',
                ];
            }
        }

        usort($errorData, static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['message']] <=> [$b['file'], $b['line'], $b['message']]);

        $errors = [];
        foreach ($errorData as $error) {
            $errors[] = RuleErrorBuilder::message($error['message'])
                ->identifier($error['identifier'])
                ->file($error['file'])
                ->line($error['line'])
                ->build();
        }

        return $errors;
    }

    /**
     * @param list<string> $path
     */
    private function violationMessage(Declarations $declarations, DeclarationRecord $sink, string $effect, ContractOrigin $origin, array $path): string
    {
        $pathDisplay = implode(' -> ', array_map(
            static fn (string $key): string => $declarations->displayName($key),
            $path,
        ));

        return sprintf(
            "%s %s %s but reaches effect '%s': %s (declares #[Effect('%s')]).",
            $sink->className !== null ? 'Method' : 'Function',
            $sink->displayName(),
            $this->contractDescription($effect, $origin),
            $effect,
            $pathDisplay,
            $effect,
        );
    }

    private function contradictionMessage(DeclarationRecord $record, string $effect, ContractOrigin $origin): string
    {
        return sprintf(
            "%s %s declares #[Effect('%s')] but %s.",
            $record->className !== null ? 'Method' : 'Function',
            $record->displayName(),
            $effect,
            $this->contractDescription($effect, $origin),
        );
    }

    private function contractDescription(string $effect, ContractOrigin $origin): string
    {
        return match ($origin->kind) {
            ContractOrigin::KIND_INHERITED => $origin->inheritedVia !== null
                ? sprintf("is #[EffectFree('%s')] (inherited from %s via %s)", $effect, $origin->inheritedFrom, $origin->inheritedVia)
                : sprintf("is #[EffectFree('%s')] (inherited from %s)", $effect, $origin->inheritedFrom),
            ContractOrigin::KIND_RULE => sprintf(
                "is required to be effect-free for '%s' by the effects rule (classPattern: '%s', methodPattern: '%s')",
                $effect,
                $origin->classPattern,
                $origin->methodPattern,
            ),
            default => sprintf("is #[EffectFree('%s')]", $effect),
        };
    }
}
