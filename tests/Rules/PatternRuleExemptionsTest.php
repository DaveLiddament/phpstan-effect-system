<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

final class PatternRuleExemptionsTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule(
            [],
            [
                [
                    'classPattern' => 'EffectTest\PatternRuleExemptions\Controller\*Controller',
                    'methodPattern' => '*',
                    'effectFree' => ['slow'],
                    'exclude' => [
                        'EffectTest\PatternRuleExemptions\Controller\DebugController',
                        'EffectTest\PatternRuleExemptions\Controller\Internal\*',
                        'EffectTest\PatternRuleExemptions\Controller\GoneController',
                    ],
                ],
            ],
            ['slow', 'io'],
            ['Tests\*', '*\Tests\*'],
        );
    }

    public function testExclusionsAndExemptionsDropOnlyPatternRuleContracts(): void
    {
        $this->analyse([__DIR__ . '/data/pattern-rule-exemptions.php'], [
            [
                "Effects rule (classPattern: 'EffectTest\\PatternRuleExemptions\\Controller\\*Controller', methodPattern: '*') excludes 'EffectTest\\PatternRuleExemptions\\Controller\\GoneController', which matches no method the rule applies to. Remove the exclusion.",
                -1,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\Db::count() has #[ExemptFromEffectRule('slow')] but no effects rule requires it to be effect-free for 'slow'. Remove the exemption.",
                19,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\HomeController::index() is required to be effect-free for 'slow' by the effects rule (classPattern: 'EffectTest\\PatternRuleExemptions\\Controller\\*Controller', methodPattern: '*') but reaches effect 'slow': EffectTest\\PatternRuleExemptions\\Controller\\HomeController::index() -> EffectTest\\PatternRuleExemptions\\Controller\\Db::query() (declares #[Effect('slow')]).",
                29,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ReportController::download() is required to be effect-free for 'slow' by the effects rule (classPattern: 'EffectTest\\PatternRuleExemptions\\Controller\\*Controller', methodPattern: '*') but reaches effect 'slow': EffectTest\\PatternRuleExemptions\\Controller\\ReportController::download() -> EffectTest\\PatternRuleExemptions\\Controller\\Db::query() (declares #[Effect('slow')]).",
                53,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ReportController::preview() has #[ExemptFromEffectRule('slow')] but does not reach effect 'slow'. Remove the exemption.",
                59,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ReportController::archive() has #[ExemptFromEffectRule('slow')] but is #[EffectFree('slow')]. Only contracts from effects rules can be exempted.",
                65,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ReportController::summary() has #[ExemptFromEffectRule('slwo')] but no effects rule requires it to be effect-free for 'slwo'. Remove the exemption.",
                71,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ReportController::summary() uses effect 'slwo', which is not listed in allowedEffects (slow, io). Fix the effect name, or add it to the effects.allowedEffects parameter.",
                71,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\DashboardController::show() is required to be effect-free for 'slow' by the effects rule (classPattern: 'EffectTest\\PatternRuleExemptions\\Controller\\*Controller', methodPattern: '*') but reaches effect 'slow': EffectTest\\PatternRuleExemptions\\Controller\\DashboardController::show() -> EffectTest\\PatternRuleExemptions\\Controller\\ReportController::render() -> EffectTest\\PatternRuleExemptions\\Controller\\Db::query() (declares #[Effect('slow')]).",
                80,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ImportController::handle() has #[ExemptFromEffectRule('slow')] but is #[EffectFree('slow')] (inherited from EffectTest\\PatternRuleExemptions\\Controller\\Job::handle()). Only contracts from effects rules can be exempted.",
                105,
            ],
            [
                "Method EffectTest\\PatternRuleExemptions\\Controller\\ImportController::handle() is #[EffectFree('slow')] (inherited from EffectTest\\PatternRuleExemptions\\Controller\\Job::handle()) but reaches effect 'slow': EffectTest\\PatternRuleExemptions\\Controller\\ImportController::handle() -> EffectTest\\PatternRuleExemptions\\Controller\\Db::query() (declares #[Effect('slow')]).",
                105,
            ],
        ]);
    }
}
