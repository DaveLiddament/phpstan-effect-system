<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Records outgoing call edges for every method/function body. Calls inside
 * inline closures and arrow functions are attributed to the enclosing named
 * method/function (Scope::getFunction() preserves it). Dynamic callables
 * (variable/string callables, dynamic method names without constant string
 * types) are skipped — documented false negatives.
 *
 * First-class callables never reach this collector: PHPStan replaces them
 * with virtual nodes handled by the *CallableCollector classes.
 *
 * @implements Collector<CallLike, array{caller: string, line: int, callees: list<array{key: string, calledClass: string|null, method: string|null, dispatch: bool}>}>
 */
final class CallCollector implements Collector
{
    public function __construct(
        private CallResolver $callResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $callees = $this->callResolver->resolveMethodCallees($node->var, $node->name, $scope);
        } elseif ($node instanceof StaticCall) {
            $callees = $this->callResolver->resolveStaticCallees($node->class, $node->name, $scope);
        } elseif ($node instanceof FuncCall) {
            $callees = $this->callResolver->resolveFunctionCallees($node->name, $scope);
        } elseif ($node instanceof New_) {
            $callees = $this->callResolver->resolveNewCallees($node, $scope);
        } else {
            $callees = [];
        }

        return $this->callResolver->buildRecord($scope, $node->getStartLine(), $callees);
    }
}
