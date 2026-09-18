<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\StaticMethodCallableNode;

/**
 * Records an edge for the first-class callable `Foo::bar(...)`. Creating the
 * callable is conservatively treated as reaching the callee's effects, because
 * the invocation site (a variable call) cannot be tracked.
 *
 * PHPStan replaces first-class-callable call expressions with dedicated
 * virtual nodes before rules/collectors run, so they never reach
 * CallCollector.
 *
 * @implements Collector<StaticMethodCallableNode, array{caller: string, line: int, callees: list<array{key: string, calledClass: string|null, method: string|null, dispatch: bool}>}>
 */
final class StaticMethodCallableCollector implements Collector
{
    public function __construct(
        private CallResolver $callResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticMethodCallableNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $callees = $this->callResolver->resolveStaticCallees($node->getClass(), $node->getName(), $scope);

        return $this->callResolver->buildRecord($scope, $node->getStartLine(), $callees);
    }
}
