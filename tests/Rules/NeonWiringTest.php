<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * Loads the real extension.neon through the DI container: proves the
 * parametersSchema, parameter defaults, service registration, %effects.*%
 * argument wiring and tagged inferrers, end to end.
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
        $inferrer = 'DaveLiddament\PhpstanEffectSystem\Tests\Rules\Inference\ConventionInferrer';

        $this->analyse([__DIR__ . '/data/stubs.php', __DIR__ . '/data/inference.php'], [
            [
                "Method EffectTest\\Inference\\OrderService::ship() is #[EffectFree('state')] but reaches effect 'state': EffectTest\\Inference\\OrderService::ship() -> EffectTest\\Inference\\OrderRepository::updateStatus() (effect 'state' inferred by {$inferrer}).",
                42,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::show() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::show() -> file_get_contents() (declares #[Effect('io')]).",
                48,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::export() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::export() -> file_put_contents() (effect 'io' inferred by the effects source (functionPattern: 'file_put_*')).",
                57,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::reset() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::reset() -> EffectTest\\Inference\\deleteAll() (effect 'io' inferred by {$inferrer}).",
                63,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::notify() is #[EffectFree('smtp')] but reaches effect 'smtp': EffectTest\\Inference\\OrderService::notify() -> EffectTest\\StubVendor\\Mailer::send() (declares #[Effect('smtp')]).",
                70,
            ],
            [
                "Method EffectTest\\Inference\\AuditRepository::updateLog() has effect 'state' (inferred by {$inferrer}) but is #[EffectFree('state')].",
                85,
            ],
            [
                "Method EffectTest\\Inference\\CustomerService::changeEmail() is #[EffectFree('state')] but reaches effect 'state': EffectTest\\Inference\\CustomerService::changeEmail() -> EffectTest\\Inference\\CustomerStore::updateEmail() (effect 'state' inferred by {$inferrer}).",
                111,
            ],
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
