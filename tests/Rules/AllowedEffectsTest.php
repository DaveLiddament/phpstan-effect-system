<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

final class AllowedEffectsTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule([], [], ['slow', 'io'], ['Tests\*', '*\Tests\*']);
    }

    public function testUnknownEffectNamesAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/allowed-effects.php'], [
            [
                "Method EffectTest\\AllowedEffects\\Repo::load() uses effect 'slwo', which is not listed in allowedEffects (slow, io). Fix the effect name, or add it to the effects.allowedEffects parameter.",
                13,
            ],
            [
                "Method EffectTest\\AllowedEffects\\Repo::find() uses effect 'database', which is not listed in allowedEffects (slow, io). Fix the effect name, or add it to the effects.allowedEffects parameter.",
                18,
            ],
        ]);
    }
}
