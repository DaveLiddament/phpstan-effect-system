<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use DaveLiddament\PhpstanEffectSystem\Graph\MethodKey;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InFunctionNode;

/**
 * Records every free-function declaration.
 *
 * @implements Collector<InFunctionNode, array{key: string, class: null, name: string, file: string, line: int, effects: list<string>, effectFree: list<string>, handles: list<string>, private: bool, abstract: bool}>
 */
final class FunctionDeclarationCollector implements Collector
{
    public function __construct(
        private EffectAttributeReader $attributeReader,
    ) {
    }

    public function getNodeType(): string
    {
        return InFunctionNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $function = $node->getFunctionReflection();
        $attributes = $this->attributeReader->read($function);

        return [
            'key' => MethodKey::forFunction($function->getName()),
            'class' => null,
            'name' => $function->getName(),
            'file' => $scope->getFile(),
            'line' => $node->getOriginalNode()->getStartLine(),
            'effects' => $attributes['effects'],
            'effectFree' => $attributes['effectFree'],
            'handles' => $attributes['handles'],
            'private' => false,
            'abstract' => false,
        ];
    }
}
