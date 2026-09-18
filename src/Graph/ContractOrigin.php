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
        public readonly ?string $inheritedVia,
        public readonly ?string $classPattern,
        public readonly ?string $methodPattern,
    ) {
    }

    public static function own(): self
    {
        return new self(self::KIND_OWN, null, null, null, null);
    }

    /**
     * @param string|null $viaClassDisplayName set when the method's own class
     *        is unrelated to the contract: a subclass pairs the inherited
     *        method with the ancestor that declares the contract
     */
    public static function inheritedFrom(string $ancestorDisplayName, ?string $viaClassDisplayName = null): self
    {
        return new self(self::KIND_INHERITED, $ancestorDisplayName, $viaClassDisplayName, null, null);
    }

    public static function fromPatternRule(string $classPattern, string $methodPattern): self
    {
        return new self(self::KIND_RULE, null, null, $classPattern, $methodPattern);
    }
}
