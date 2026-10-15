<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use DaveLiddament\PhpstanEffectSystem\Graph\DeclarationRecord;
use DaveLiddament\PhpstanEffectSystem\Graph\MethodKey;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InFunctionNode;

/**
 * Records every free-function declaration.
 *
 * @implements Collector<InFunctionNode, string>
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

    public function processNode(Node $node, Scope $scope): string
    {
        $function = $node->getFunctionReflection();
        $attributes = $this->attributeReader->read($function);

        return (new DeclarationRecord(
            MethodKey::forFunction($function->getName()),
            null,
            $function->getName(),
            $scope->getFile(),
            $node->getOriginalNode()->getStartLine(),
            $attributes['effects'],
            $attributes['effectFree'],
            $attributes['handles'],
            exemptFromRules: $attributes['exemptFromRules'],
        ))->encode();
    }
}
