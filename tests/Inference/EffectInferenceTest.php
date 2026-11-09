<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Inference;

use DaveLiddament\PhpstanEffectSystem\Inference\EffectInference;
use DaveLiddament\PhpstanEffectSystem\Inference\EffectInferrer;
use InvalidArgumentException;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Testing\PHPStanTestCase;

final class EffectInferenceTest extends PHPStanTestCase
{
    public function testSourceEffectOutsideAllowedEffectsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("effects source #1 uses effect 'htpp', which is not listed in allowedEffects (http, io)");

        new EffectInference(
            [
                ['functionPattern' => 'file_put_*', 'effects' => ['io']],
                ['methodPattern' => 'request', 'classPattern' => 'GuzzleHttp\*', 'effects' => ['htpp']],
            ],
            ['http', 'io'],
            [],
        );
    }

    public function testInferredEffectOutsideAllowedEffectsNamesTheInferrer(): void
    {
        $inferrer = new class () extends EffectInferrer {
            public function effectsOfFunction(FunctionReflection $function): array
            {
                return ['htpp'];
            }
        };
        $inference = new EffectInference([], ['http'], [$inferrer]);
        $function = self::createReflectionProvider()->getFunction(new FullyQualified('file_put_contents'), null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(sprintf("inferrer %s, for file_put_contents(), uses effect 'htpp', which is not listed in allowedEffects (http)", $inferrer::class));

        $inference->inferForFunction($function);
    }
}
