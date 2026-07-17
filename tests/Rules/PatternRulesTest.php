<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

final class PatternRulesTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule(
            [],
            [
                [
                    'classPattern' => 'EffectTest\PatternRules\Controller\*Controller',
                    'methodPattern' => '*Action',
                    'effectFree' => ['slow'],
                ],
            ],
            [],
            ['Tests\*', '*\Tests\*'],
        );
    }

    public function testPatternRuleEnforcesEffectFreeWithoutAttributes(): void
    {
        $this->analyse([__DIR__ . '/data/pattern-rules.php'], [
            [
                "Method EffectTest\\PatternRules\\Controller\\HomeController::indexAction() is required to be effect-free for 'slow' by the effects rule (classPattern: 'EffectTest\\PatternRules\\Controller\\*Controller', methodPattern: '*Action') but reaches effect 'slow': EffectTest\\PatternRules\\Controller\\HomeController::indexAction() -> EffectTest\\PatternRules\\Controller\\Db::query() (declares #[Effect('slow')]).",
                19,
            ],
        ]);
    }
}
