<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Rules\Inference;

use DaveLiddament\PhpstanEffectSystem\Inference\EffectInferrer;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;

/**
 * The conventions of tests/Rules/data/inference.php, written as an inferrer.
 * InferenceSourcesTest expresses the same conventions as configuration.
 */
final class ConventionInferrer extends EffectInferrer
{
    public function effectsOfMethod(ExtendedMethodReflection $method): array
    {
        $class = $method->getDeclaringClass();
        $isRepository = $class->is('EffectTest\Inference\Repository')
            || array_any($class->getAttributes(), static fn (AttributeReflection $attribute): bool => $attribute->getName() === 'EffectTest\Inference\AsRepository');
        if ($isRepository && str_starts_with(strtolower($method->getName()), 'update')) {
            return ['state'];
        }
        if ($class->getName() === 'EffectTest\StubVendor\Mailer' && $method->getName() === 'send') {
            return ['smtp'];
        }

        return [];
    }

    public function effectsOfFunction(FunctionReflection $function): array
    {
        $name = strtolower($function->getName());
        if ($name === 'file_put_contents' || str_starts_with($name, 'effecttest\inference\delete')) {
            return ['io'];
        }

        return [];
    }
}
