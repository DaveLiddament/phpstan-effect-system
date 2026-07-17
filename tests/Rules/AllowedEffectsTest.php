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
                "Method EffectTest\\AllowedEffects\\Repo::load() uses unknown effect 'slwo'. Allowed effects: slow, io.",
                13,
            ],
            [
                "Method EffectTest\\AllowedEffects\\Repo::find() uses unknown effect 'database'. Allowed effects: slow, io.",
                18,
            ],
        ]);
    }
}
