<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

/**
 * allowedEffects is mandatory: without it every effect name is unknown.
 */
final class AllowedEffectsNotConfiguredTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule([], [], [], ['Tests\*', '*\Tests\*']);
    }

    public function testEveryEffectNameIsReportedWhenAllowedEffectsIsNotConfigured(): void
    {
        $this->analyse([__DIR__ . '/data/allowed-effects.php'], [
            [
                "Method EffectTest\\AllowedEffects\\Repo::load() uses effect 'slwo', which is not listed in allowedEffects (none configured). Every effect name must be listed in the effects.allowedEffects parameter.",
                13,
            ],
            [
                "Method EffectTest\\AllowedEffects\\Repo::find() uses effect 'database', which is not listed in allowedEffects (none configured). Every effect name must be listed in the effects.allowedEffects parameter.",
                18,
            ],
            [
                "Method EffectTest\\AllowedEffects\\Repo::cached() uses effect 'slow', which is not listed in allowedEffects (none configured). Every effect name must be listed in the effects.allowedEffects parameter.",
                23,
            ],
        ]);
    }
}
