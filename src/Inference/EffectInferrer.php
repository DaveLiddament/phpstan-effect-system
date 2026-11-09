<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Inference;

use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;

/**
 * Gives methods and functions effects by convention, without attributes, for
 * rules too specific for the `sources` configuration (e.g. "every update*()
 * method of a class implementing Repository mutates state").
 *
 * Extend it, override either method, and register the subclass as a service
 * tagged `effects.inferrer`. It is asked about every analysed method and
 * function, and about every unanalysed one (vendor or built-in) that analysed
 * code calls. An inferred effect behaves exactly like #[Effect].
 */
abstract class EffectInferrer
{
    /**
     * @return list<string> effects the method has
     */
    public function effectsOfMethod(ExtendedMethodReflection $method): array
    {
        return [];
    }

    /**
     * @return list<string> effects the function has
     */
    public function effectsOfFunction(FunctionReflection $function): array
    {
        return [];
    }
}
