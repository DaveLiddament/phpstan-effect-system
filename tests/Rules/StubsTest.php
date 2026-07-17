<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

final class StubsTest extends EffectSystemRuleTestCase
{
    protected function getRule(): Rule
    {
        return new EffectSystemRule(
            [
                ['function' => 'file_get_contents', 'effects' => ['io']],
                ['method' => 'EffectTest\StubVendor\Mailer::send', 'effects' => ['smtp']],
            ],
            [],
            [],
            ['Tests\*', '*\Tests\*'],
        );
    }

    public function testStubbedVendorEffectsPropagate(): void
    {
        $this->analyse([__DIR__ . '/data/stubs.php'], [
            [
                "Method EffectTest\\Stubs\\Service::fetch() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Stubs\\Service::fetch() -> EffectTest\\Stubs\\HttpWrapper::get() -> file_get_contents() (declares #[Effect('io')]).",
                20,
            ],
            [
                "Method EffectTest\\Stubs\\Service::notify() is #[EffectFree('smtp')] but reaches effect 'smtp': EffectTest\\Stubs\\Service::notify() -> EffectTest\\StubVendor\\Mailer::send() (declares #[Effect('smtp')]).",
                26,
            ],
        ]);
    }
}
