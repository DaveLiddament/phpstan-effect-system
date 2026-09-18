<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodCallableNode;

/**
 * Records an edge for the first-class callable `$obj->method(...)`. Creating the
 * callable is conservatively treated as reaching the callee's effects, because
 * the invocation site (a variable call) cannot be tracked.
 *
 * PHPStan replaces first-class-callable call expressions with dedicated
 * virtual nodes before rules/collectors run, so they never reach
 * CallCollector.
 *
 * @implements Collector<MethodCallableNode, array{caller: string, callees: list<array{key: string, calledClass: string|null, method: string|null, dispatch: bool}>}>
 */
final class MethodCallableCollector implements Collector
{
    public function __construct(
        private CallResolver $callResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCallableNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $callees = $this->callResolver->resolveMethodCallees($node->getVar(), $node->getName(), $scope);

        return $this->callResolver->buildRecord($scope, $callees);
    }
}
