<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * Loads the real extension.neon through the DI container: proves the
 * parametersSchema, parameter defaults, service registration and %effects.*%
 * argument wiring, end to end.
 *
 * @extends RuleTestCase<EffectSystemRule>
 */
final class NeonWiringTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return array_merge(parent::getAdditionalConfigFiles(), [__DIR__ . '/config/effects.neon']);
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(EffectSystemRule::class);
    }

    protected function getCollectors(): array
    {
        $collectors = [];
        foreach (self::getContainer()->getServicesByTag('phpstan.collector') as $service) {
            if (!$service instanceof Collector) {
                continue;
            }
            $collectors[] = $service;
        }

        return $collectors;
    }

    public function testExtensionNeonWiresCollectorsRuleAndParameters(): void
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
