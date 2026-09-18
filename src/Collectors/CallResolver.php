<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Collectors;

use DaveLiddament\PhpstanEffectSystem\Graph\MethodKey;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Shared caller/callee resolution used by the call collectors.
 *
 * @phpstan-type Callee array{key: string, calledClass: string|null, method: string|null, dispatch: bool}
 */
final class CallResolver
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function resolveCaller(Scope $scope): ?string
    {
        $function = $scope->getFunction();
        if ($function === null) {
            // Global-scope code is outside the effect system.
            return null;
        }

        if ($function instanceof MethodReflection) {
            // Property hooks land here too, under a '$property::hook' name no
            // call can ever target: their edges are inert, which is how the
            // documented "hooks are not tracked" limitation is kept.
            return MethodKey::forMethod($function->getDeclaringClass()->getName(), $function->getName());
        }

        return MethodKey::forFunction($function->getName());
    }

    /**
     * @param list<Callee> $callees
     * @return array{caller: string, callees: list<Callee>}|null
     */
    public function buildRecord(Scope $scope, array $callees): ?array
    {
        if ($callees === []) {
            return null;
        }

        $caller = $this->resolveCaller($scope);
        if ($caller === null) {
            return null;
        }

        return ['caller' => $caller, 'callees' => $callees];
    }

    /**
     * @return list<Callee>
     */
    public function resolveMethodCallees(Expr $var, Node\Identifier|Expr $name, Scope $scope): array
    {
        $callees = [];
        $calledOnType = $scope->getType($var);
        foreach ($this->resolveCallNames($name, $scope) as $methodName) {
            foreach ($calledOnType->getObjectClassReflections() as $classReflection) {
                if (!$classReflection->hasMethod($methodName)) {
                    continue;
                }
                $method = $classReflection->getMethod($methodName, $scope);
                $dispatch = !$method->isPrivate()
                    && !$method->isFinal()->yes()
                    && !$classReflection->isFinal();

                $callees[] = [
                    'key' => MethodKey::forMethod($method->getDeclaringClass()->getName(), $method->getName()),
                    'calledClass' => $classReflection->getName(),
                    'method' => $method->getName(),
                    'dispatch' => $dispatch,
                ];
            }
        }

        return $callees;
    }

    /**
     * @return list<Callee>
     */
    public function resolveStaticCallees(Node\Name|Expr $class, Node\Identifier|Expr $name, Scope $scope): array
    {
        if ($class instanceof Node\Name) {
            // Only static:: is subject to late static binding; self::, parent::
            // and explicit class names resolve to exactly one implementation.
            $lateStaticBinding = $class->toLowerString() === 'static';
            $calledOnType = $scope->resolveTypeByName($class);
        } else {
            // $object::method() / $classString::method(): the runtime class
            // may be any subtype of the static type.
            $lateStaticBinding = true;
            $calledOnType = $scope->getType($class)->getObjectTypeOrClassStringObjectType();
        }

        $callees = [];
        foreach ($this->resolveCallNames($name, $scope) as $methodName) {
            foreach ($calledOnType->getObjectClassReflections() as $classReflection) {
                if (!$classReflection->hasMethod($methodName)) {
                    continue;
                }
                $method = $classReflection->getMethod($methodName, $scope);
                $dispatch = $lateStaticBinding
                    && !$method->isPrivate()
                    && !$method->isFinal()->yes()
                    && !$classReflection->isFinal();

                $callees[] = [
                    'key' => MethodKey::forMethod($method->getDeclaringClass()->getName(), $method->getName()),
                    'calledClass' => $classReflection->getName(),
                    'method' => $method->getName(),
                    'dispatch' => $dispatch,
                ];
            }
        }

        return $callees;
    }

    /**
     * @return list<Callee>
     */
    public function resolveFunctionCallees(Node\Name|Expr $name, Scope $scope): array
    {
        if (!$name instanceof Node\Name) {
            // Variable/expression callables are not tracked.
            return [];
        }

        if (!$this->reflectionProvider->hasFunction($name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($name, $scope);

        return [[
            'key' => MethodKey::forFunction($function->getName()),
            'calledClass' => null,
            'method' => null,
            'dispatch' => false,
        ]];
    }

    /**
     * @return list<Callee>
     */
    public function resolveNewCallees(New_ $node, Scope $scope): array
    {
        if ($node->class instanceof Node\Name) {
            // new static() may instantiate any subclass.
            $lateStaticBinding = $node->class->toLowerString() === 'static';
            $type = $scope->resolveTypeByName($node->class);
        } elseif ($node->class instanceof Node\Stmt\Class_) {
            // Anonymous class: the reflection's internal name is stable
            // (derived from file + position).
            $lateStaticBinding = false;
            $type = $scope->getType($node);
        } else {
            // new $classString() / new $object(): any subtype of the static
            // type may be instantiated.
            $lateStaticBinding = true;
            $type = $scope->getType($node->class)->getObjectTypeOrClassStringObjectType();
        }

        $callees = [];
        foreach ($type->getObjectClassReflections() as $classReflection) {
            if (!$classReflection->hasConstructor()) {
                if ($lateStaticBinding && !$classReflection->isFinal()) {
                    // No constructor here, but a subclass may declare one.
                    $callees[] = [
                        'key' => MethodKey::forMethod($classReflection->getName(), '__construct'),
                        'calledClass' => $classReflection->getName(),
                        'method' => '__construct',
                        'dispatch' => true,
                    ];
                }
                continue;
            }
            $constructor = $classReflection->getConstructor();
            $dispatch = $lateStaticBinding
                && !$constructor->isFinal()->yes()
                && !$classReflection->isFinal();

            $callees[] = [
                'key' => MethodKey::forMethod($constructor->getDeclaringClass()->getName(), '__construct'),
                'calledClass' => $classReflection->getName(),
                'method' => '__construct',
                'dispatch' => $dispatch,
            ];
        }

        return $callees;
    }

    /**
     * @return list<string>
     */
    private function resolveCallNames(Node\Identifier|Expr $name, Scope $scope): array
    {
        if ($name instanceof Node\Identifier) {
            return [$name->toString()];
        }

        $names = [];
        foreach ($scope->getType($name)->getConstantStrings() as $constantString) {
            $names[] = $constantString->getValue();
        }

        return $names;
    }
}
