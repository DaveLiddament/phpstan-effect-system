<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Config;

use DaveLiddament\PhpstanEffectSystem\Graph\DeclarationRecord;
use DaveLiddament\PhpstanEffectSystem\Graph\MethodKey;
use InvalidArgumentException;

/**
 * Validated extension configuration. Configuration mistakes (malformed stub
 * identifiers, effect names outside allowedEffects) fail fast with an
 * exception: unlike attribute typos there is no source line to attach a
 * regular error to.
 */
final class EffectsConfig
{
    /**
     * @param list<DeclarationRecord> $stubRecords
     * @param list<array{classPattern: string, methodPattern: string, effectFree: list<string>}> $patternRules
     * @param list<string> $allowedEffects
     * @param list<string> $excludeImplementationsFrom
     */
    private function __construct(
        public readonly array $stubRecords,
        public readonly array $patternRules,
        public readonly array $allowedEffects,
        public readonly array $excludeImplementationsFrom,
    ) {
    }

    /**
     * @param list<array{method?: string, function?: string, effects?: list<string>}> $stubs
     * @param list<array{classPattern?: string, methodPattern?: string, effectFree?: list<string>}> $rules
     * @param list<string> $allowedEffects
     * @param list<string> $excludeImplementationsFrom
     */
    public static function fromParameters(array $stubs, array $rules, array $allowedEffects, array $excludeImplementationsFrom): self
    {
        $stubRecords = [];
        foreach ($stubs as $index => $stub) {
            $stubRecords[] = self::parseStub($stub, $index, $allowedEffects);
        }

        $patternRules = [];
        foreach ($rules as $index => $rule) {
            $where = sprintf('effects rule #%d', $index);
            $classPattern = $rule['classPattern'] ?? null;
            $methodPattern = $rule['methodPattern'] ?? null;
            $effectFree = $rule['effectFree'] ?? [];
            if ($classPattern === null || $methodPattern === null) {
                throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must define "classPattern" and "methodPattern".', $where));
            }
            if ($effectFree === []) {
                throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must list at least one effect in "effectFree".', $where));
            }
            self::assertKnownEffects($effectFree, $allowedEffects, $where);

            $patternRules[] = [
                'classPattern' => $classPattern,
                'methodPattern' => $methodPattern,
                'effectFree' => $effectFree,
            ];
        }

        return new self($stubRecords, $patternRules, $allowedEffects, $excludeImplementationsFrom);
    }

    /**
     * @param array{method?: string, function?: string, effects?: list<string>} $stub
     * @param list<string> $allowedEffects
     */
    private static function parseStub(array $stub, int $index, array $allowedEffects): DeclarationRecord
    {
        $where = sprintf('effects stub #%d', $index);
        $hasMethod = isset($stub['method']);
        $hasFunction = isset($stub['function']);
        if ($hasMethod === $hasFunction) {
            throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must define exactly one of "method" or "function".', $where));
        }

        $effects = $stub['effects'] ?? [];
        if ($effects === []) {
            throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s must list at least one effect.', $where));
        }
        self::assertKnownEffects($effects, $allowedEffects, $where);

        if (isset($stub['function'])) {
            $functionName = ltrim($stub['function'], '\\');

            return new DeclarationRecord(
                MethodKey::forFunction($functionName),
                null,
                $functionName,
                null,
                null,
                $effects,
                [],
                [],
            );
        }

        $parts = explode('::', $stub['method'] ?? '');
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException(sprintf('phpstan-effect-system: %s "method" must look like "Fully\Qualified\ClassName::methodName".', $where));
        }
        $className = ltrim($parts[0], '\\');

        return new DeclarationRecord(
            MethodKey::forMethod($className, $parts[1]),
            $className,
            $parts[1],
            null,
            null,
            $effects,
            [],
            [],
        );
    }

    /**
     * @param list<string> $effects
     * @param list<string> $allowedEffects
     */
    private static function assertKnownEffects(array $effects, array $allowedEffects, string $where): void
    {
        if ($allowedEffects === []) {
            return;
        }

        foreach ($effects as $effect) {
            if (!in_array($effect, $allowedEffects, true)) {
                throw new InvalidArgumentException(sprintf(
                    "phpstan-effect-system: %s uses unknown effect '%s'. Allowed effects: %s.",
                    $where,
                    $effect,
                    implode(', ', $allowedEffects),
                ));
            }
        }
    }
}
