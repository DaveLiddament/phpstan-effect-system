<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Where an EffectFree contract on a method came from: its own attribute, an
 * attribute inherited from an overridden/implemented method, or a
 * pattern-based rule in the configuration.
 */
final class ContractOrigin
{
    public const KIND_OWN = 'own';
    public const KIND_INHERITED = 'inherited';
    public const KIND_RULE = 'rule';

    private function __construct(
        public readonly string $kind,
        public readonly ?string $inheritedFrom,
        public readonly ?string $classPattern,
        public readonly ?string $methodPattern,
    ) {
    }

    public static function own(): self
    {
        return new self(self::KIND_OWN, null, null, null);
    }

    public static function inheritedFrom(string $ancestorDisplayName): self
    {
        return new self(self::KIND_INHERITED, $ancestorDisplayName, null, null);
    }

    public static function fromPatternRule(string $classPattern, string $methodPattern): self
    {
        return new self(self::KIND_RULE, null, $classPattern, $methodPattern);
    }
}
