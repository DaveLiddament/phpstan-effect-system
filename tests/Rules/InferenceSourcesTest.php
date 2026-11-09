<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Inference\EffectInference;
use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use PHPStan\Rules\Rule;

/**
 * The conventions of InferrersTest, expressed as `sources` configuration
 * instead of an EffectInferrer.
 */
final class InferenceSourcesTest extends EffectSystemRuleTestCase
{
    protected function getEffectInference(): EffectInference
    {
        return new EffectInference(
            [
                ['instanceOf' => '\EffectTest\Inference\Repository', 'methodPattern' => 'update*', 'effects' => ['state']],
                ['classHasAttribute' => '\EffectTest\Inference\AsRepository', 'methodPattern' => 'update*', 'effects' => ['state']],
                ['classPattern' => 'EffectTest\StubVendor\*', 'methodPattern' => 'send', 'effects' => ['smtp']],
                ['functionPattern' => 'file_put_*', 'effects' => ['io']],
                ['functionPattern' => 'EffectTest\Inference\delete*', 'effects' => ['io']],
            ],
            self::ALLOWED_EFFECTS,
            [],
        );
    }

    protected function getRule(): Rule
    {
        return new EffectSystemRule(
            [['method' => 'EffectTest\StubVendor\Mailer::send', 'effects' => ['slow']]],
            [],
            self::ALLOWED_EFFECTS,
            ['Tests\*', '*\Tests\*'],
            $this->getEffectInference(),
            self::createReflectionProvider(),
        );
    }

    public function testSourcesInferEffectsWithoutAttributes(): void
    {
        $repositories = "the effects source (instanceOf: 'EffectTest\\Inference\\Repository', methodPattern: 'update*')";

        $this->analyse([__DIR__ . '/data/inference.php'], [
            [
                "Method EffectTest\\Inference\\OrderService::ship() is #[EffectFree('state')] but reaches effect 'state': EffectTest\\Inference\\OrderService::ship() -> EffectTest\\Inference\\OrderRepository::updateStatus() (effect 'state' inferred by {$repositories}).",
                42,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::export() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::export() -> file_put_contents() (effect 'io' inferred by the effects source (functionPattern: 'file_put_*')).",
                57,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::reset() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::reset() -> EffectTest\\Inference\\deleteAll() (effect 'io' inferred by the effects source (functionPattern: 'EffectTest\\Inference\\delete*')).",
                63,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::notify() is #[EffectFree('smtp')] but reaches effect 'smtp': EffectTest\\Inference\\OrderService::notify() -> EffectTest\\StubVendor\\Mailer::send() (effect 'smtp' inferred by the effects source (classPattern: 'EffectTest\\StubVendor\\*', methodPattern: 'send')).",
                70,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::notifyLater() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\Inference\\OrderService::notifyLater() -> EffectTest\\StubVendor\\Mailer::send() (declares #[Effect('slow')]).",
                76,
            ],
            [
                "Method EffectTest\\Inference\\AuditRepository::updateLog() has effect 'state' (inferred by {$repositories}) but is #[EffectFree('state')].",
                85,
            ],
            [
                "Method EffectTest\\Inference\\CustomerService::changeEmail() is #[EffectFree('state')] but reaches effect 'state': EffectTest\\Inference\\CustomerService::changeEmail() -> EffectTest\\Inference\\CustomerStore::updateEmail() (effect 'state' inferred by the effects source (classHasAttribute: 'EffectTest\\Inference\\AsRepository', methodPattern: 'update*')).",
                111,
            ],
        ]);
    }
}
