<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use DaveLiddament\PhpstanEffectSystem\Graph\MethodKey;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassMethodNode;

/**
 * Records every class/interface/trait method declaration (with or without
 * effect attributes). Trait methods are analysed once per using class, so they
 * are recorded per using class — exactly what the call graph needs.
 *
 * @implements Collector<InClassMethodNode, array{key: string, class: string, name: string, file: string, line: int, effects: list<string>, effectFree: list<string>, handles: list<string>, private: bool}>
 */
final class MethodDeclarationCollector implements Collector
{
    public function __construct(
        private EffectAttributeReader $attributeReader,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $method = $node->getMethodReflection();
        if ($method->isPropertyHook()) {
            return null;
        }

        $classReflection = $node->getClassReflection();
        $attributes = $this->attributeReader->read($method);

        $file = $scope->getFile();
        if ($scope->isInTrait()) {
            $traitFile = $scope->getTraitReflection()->getFileName();
            if ($traitFile !== null) {
                $file = $traitFile;
            }
        }

        return [
            'key' => MethodKey::forMethod($classReflection->getName(), $method->getName()),
            'class' => $classReflection->getName(),
            'name' => $method->getName(),
            'file' => $file,
            'line' => $node->getOriginalNode()->getStartLine(),
            'effects' => $attributes['effects'],
            'effectFree' => $attributes['effectFree'],
            'handles' => $attributes['handles'],
            'private' => $method->isPrivate(),
        ];
    }
}
