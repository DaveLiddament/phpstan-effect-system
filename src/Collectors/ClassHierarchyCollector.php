<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Records the class hierarchy facts phase 2 needs for dynamic-dispatch
 * expansion and EffectFree contract inheritance.
 *
 * @implements Collector<InClassNode, array{class: string, parents: list<string>, interfaces: list<string>, isInterface: bool, isAbstract: bool, isFinal: bool, isAnonymous: bool}>
 */
final class ClassHierarchyCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        $interfaces = [];
        foreach ($classReflection->getInterfaces() as $interface) {
            $interfaces[] = $interface->getName();
        }

        return [
            'class' => $classReflection->getName(),
            'parents' => $classReflection->getParentClassesNames(),
            'interfaces' => $interfaces,
            'isInterface' => $classReflection->isInterface(),
            'isAbstract' => $classReflection->isAbstract(),
            'isFinal' => $classReflection->isFinal(),
            'isAnonymous' => $classReflection->isAnonymous(),
        ];
    }
}
