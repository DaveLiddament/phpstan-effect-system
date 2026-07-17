<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\FunctionCallableNode;
use PHPStan\Node\MethodCallableNode;
use PHPStan\Node\StaticMethodCallableNode;
use PHPStan\Node\VirtualNode;

/**
 * Records edges for first-class callables (`$obj->method(...)`, `strlen(...)`,
 * `Foo::bar(...)`). Creating the callable is conservatively treated as
 * reaching the callee's effects, because the invocation site (a variable
 * call) cannot be tracked.
 *
 * PHPStan replaces first-class-callable call expressions with dedicated
 * virtual nodes before rules/collectors run, so this collector listens on
 * VirtualNode and filters.
 *
 * @implements Collector<Node, array{caller: string, line: int, callees: list<array{key: string, calledClass: string|null, method: string|null, dispatch: bool}>}>
 */
final class FirstClassCallableCollector implements Collector
{
    public function __construct(
        private CallResolver $callResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return VirtualNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($node instanceof MethodCallableNode) {
            $callees = $this->callResolver->resolveMethodCallees($node->getVar(), $node->getName(), $scope);
        } elseif ($node instanceof FunctionCallableNode) {
            $callees = $this->callResolver->resolveFunctionCallees($node->getName(), $scope);
        } elseif ($node instanceof StaticMethodCallableNode) {
            $callees = $this->callResolver->resolveStaticCallees($node->getClass(), $node->getName(), $scope);
        } else {
            return null;
        }

        if ($callees === []) {
            return null;
        }

        $caller = $this->callResolver->resolveCaller($scope);
        if ($caller === null) {
            return null;
        }

        return [
            'caller' => $caller,
            'line' => $node->getStartLine(),
            'callees' => $callees,
        ];
    }
}
