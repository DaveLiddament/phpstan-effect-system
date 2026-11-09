<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Inference;

use DaveLiddament\PhpstanEffectSystem\Config\EffectsConfig;
use InvalidArgumentException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;

use const FNM_CASEFOLD;
use const FNM_NOESCAPE;

/**
 * Infers effects for methods and functions from the `sources` configuration
 * and the registered EffectInferrer services. Each inferred effect is paired
 * with a description of what inferred it, for error messages.
 */
final class EffectInference
{
    /** @var list<array{classPattern: string|null, instanceOf: string|null, classHasAttribute: string|null, methodPattern: string|null, functionPattern: string|null, effects: list<string>, label: string}> */
    private array $sources = [];

    /**
     * @param list<array{classPattern?: string, instanceOf?: string, classHasAttribute?: string, methodPattern?: string, functionPattern?: string, effects?: list<string>}> $sources
     * @param list<string> $allowedEffects
     * @param list<EffectInferrer> $inferrers
     */
    public function __construct(
        array $sources,
        private array $allowedEffects,
        private array $inferrers,
    ) {
        foreach ($sources as $index => $source) {
            $where = sprintf('effects source #%d', $index);
            $isFunction = isset($source['functionPattern']);
            $isMethod = isset($source['methodPattern']);
            if ($isFunction === $isMethod) {
                throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must define exactly one of "methodPattern" or "functionPattern".', $where));
            }
            if ($isFunction && (isset($source['classPattern']) || isset($source['instanceOf']) || isset($source['classHasAttribute']))) {
                throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s cannot combine "functionPattern" with "classPattern", "instanceOf" or "classHasAttribute".', $where));
            }
            $effects = $source['effects'] ?? [];
            if ($effects === []) {
                throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must list at least one effect.', $where));
            }
            EffectsConfig::assertKnownEffects($effects, $allowedEffects, $where);

            $parsed = [
                'classPattern' => $source['classPattern'] ?? null,
                'instanceOf' => isset($source['instanceOf']) ? ltrim($source['instanceOf'], '\\') : null,
                'classHasAttribute' => isset($source['classHasAttribute']) ? ltrim($source['classHasAttribute'], '\\') : null,
                'methodPattern' => $source['methodPattern'] ?? null,
                'functionPattern' => $source['functionPattern'] ?? null,
                'effects' => $effects,
            ];
            $labelParts = [];
            foreach (['classPattern', 'instanceOf', 'classHasAttribute', 'methodPattern', 'functionPattern'] as $field) {
                if ($parsed[$field] !== null) {
                    $labelParts[] = sprintf("%s: '%s'", $field, $parsed[$field]);
                }
            }
            $parsed['label'] = sprintf('the effects source (%s)', implode(', ', $labelParts));
            $this->sources[] = $parsed;
        }
    }

    public function isEmpty(): bool
    {
        return $this->sources === [] && $this->inferrers === [];
    }

    /**
     * @return array<string, string> effect => what inferred it
     */
    public function inferForMethod(ExtendedMethodReflection $method): array
    {
        $class = $method->getDeclaringClass();
        $inferred = [];
        foreach ($this->sources as $source) {
            if ($source['methodPattern'] === null || !self::matches($source['methodPattern'], $method->getName())) {
                continue;
            }
            if ($source['classPattern'] !== null && !self::matches($source['classPattern'], $class->getName())) {
                continue;
            }
            if ($source['instanceOf'] !== null && !$class->is($source['instanceOf'])) {
                continue;
            }
            if ($source['classHasAttribute'] !== null && !self::hasAttribute($class, $source['classHasAttribute'])) {
                continue;
            }
            $inferred = $this->add($inferred, $source['effects'], $source['label']);
        }

        foreach ($this->inferrers as $inferrer) {
            $effects = $inferrer->effectsOfMethod($method);
            $this->assertKnown($effects, $inferrer, $class->getName() . '::' . $method->getName() . '()');
            $inferred = $this->add($inferred, $effects, $inferrer::class);
        }

        return $inferred;
    }

    /**
     * @return array<string, string> effect => what inferred it
     */
    public function inferForFunction(FunctionReflection $function): array
    {
        $inferred = [];
        foreach ($this->sources as $source) {
            if ($source['functionPattern'] === null || !self::matches($source['functionPattern'], $function->getName())) {
                continue;
            }
            $inferred = $this->add($inferred, $source['effects'], $source['label']);
        }

        foreach ($this->inferrers as $inferrer) {
            $effects = $inferrer->effectsOfFunction($function);
            $this->assertKnown($effects, $inferrer, $function->getName() . '()');
            $inferred = $this->add($inferred, $effects, $inferrer::class);
        }

        return $inferred;
    }

    /**
     * The first source to infer an effect is the one named in messages.
     *
     * @param array<string, string> $inferred
     * @param list<string> $effects
     * @return array<string, string>
     */
    private function add(array $inferred, array $effects, string $label): array
    {
        foreach ($effects as $effect) {
            $inferred[$effect] ??= $label;
        }

        return $inferred;
    }

    /**
     * An inferrer is project code, so a name outside allowedEffects is a bug
     * in it, reported like a configuration mistake.
     *
     * @param list<string> $effects
     */
    private function assertKnown(array $effects, EffectInferrer $inferrer, string $subject): void
    {
        EffectsConfig::assertKnownEffects($effects, $this->allowedEffects, sprintf('inferrer %s, for %s,', $inferrer::class, $subject));
    }

    /**
     * Only the class's own attributes count: PHP attributes are not
     * inherited.
     */
    private static function hasAttribute(ClassReflection $class, string $attributeClass): bool
    {
        foreach ($class->getAttributes() as $attribute) {
            if (strcasecmp($attribute->getName(), $attributeClass) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $pattern, string $name): bool
    {
        return fnmatch($pattern, $name, FNM_NOESCAPE | FNM_CASEFOLD);
    }
}
