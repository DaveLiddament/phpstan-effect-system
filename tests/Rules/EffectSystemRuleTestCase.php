<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Collectors\CallCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\CallResolver;
use DaveLiddament\PhpstanEffectSystem\Collectors\ClassHierarchyCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\EffectAttributeReader;
use DaveLiddament\PhpstanEffectSystem\Collectors\FunctionCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\FunctionDeclarationCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\MethodCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\MethodDeclarationCollector;
use DaveLiddament\PhpstanEffectSystem\Collectors\StaticMethodCallableCollector;
use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EffectSystemRule>
 */
abstract class EffectSystemRuleTestCase extends RuleTestCase
{
    protected function getCollectors(): array
    {
        $reader = new EffectAttributeReader();
        $resolver = new CallResolver(self::createReflectionProvider());

        return [
            new MethodDeclarationCollector($reader),
            new FunctionDeclarationCollector($reader),
            new CallCollector($resolver),
            new MethodCallableCollector($resolver),
            new StaticMethodCallableCollector($resolver),
            new FunctionCallableCollector($resolver),
            new ClassHierarchyCollector(),
        ];
    }
}
