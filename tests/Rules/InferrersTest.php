<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules;

use DaveLiddament\PhpstanEffectSystem\Inference\EffectInference;
use DaveLiddament\PhpstanEffectSystem\Rules\EffectSystemRule;
use DaveLiddament\PhpstanEffectSystem\Tests\Rules\Inference\ConventionInferrer;
use PHPStan\Rules\Rule;

final class InferrersTest extends EffectSystemRuleTestCase
{
    protected function getEffectInference(): EffectInference
    {
        return new EffectInference([], self::ALLOWED_EFFECTS, [new ConventionInferrer()]);
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

    public function testInferredEffectsBehaveLikeDeclaredOnes(): void
    {
        $by = 'DaveLiddament\PhpstanEffectSystem\Tests\Rules\Inference\ConventionInferrer';

        $this->analyse([__DIR__ . '/data/inference.php'], [
            [
                "Method EffectTest\\Inference\\OrderService::ship() is #[EffectFree('state')] but reaches effect 'state': EffectTest\\Inference\\OrderService::ship() -> EffectTest\\Inference\\OrderRepository::updateStatus() (effect 'state' inferred by {$by}).",
                42,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::export() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::export() -> file_put_contents() (effect 'io' inferred by {$by}).",
                57,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::reset() is #[EffectFree('io')] but reaches effect 'io': EffectTest\\Inference\\OrderService::reset() -> EffectTest\\Inference\\deleteAll() (effect 'io' inferred by {$by}).",
                63,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::notify() is #[EffectFree('smtp')] but reaches effect 'smtp': EffectTest\\Inference\\OrderService::notify() -> EffectTest\\StubVendor\\Mailer::send() (effect 'smtp' inferred by {$by}).",
                70,
            ],
            [
                "Method EffectTest\\Inference\\OrderService::notifyLater() is #[EffectFree('slow')] but reaches effect 'slow': EffectTest\\Inference\\OrderService::notifyLater() -> EffectTest\\StubVendor\\Mailer::send() (declares #[Effect('slow')]).",
                76,
            ],
            [
                "Method EffectTest\\Inference\\AuditRepository::updateLog() has effect 'state' (inferred by {$by}) but is #[EffectFree('state')].",
                85,
            ],
        ]);
    }
}
