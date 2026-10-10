<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

final class DeclarationRecord
{
    /**
     * @param list<string> $effects
     * @param list<string> $effectFree
     * @param list<string> $handles
     * @param list<string> $exemptFromRules effects whose pattern-rule contracts this method is exempt from
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $className,
        public readonly string $name,
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly array $effects,
        public readonly array $effectFree,
        public readonly array $handles,
        public readonly bool $private = false,
        public readonly bool $abstract = false,
        public readonly array $exemptFromRules = [],
    ) {
    }

    /**
     * Encodes the record as one string for the collectors. PHPStan keeps every
     * collected record in memory and in the result cache, and one string costs
     * far less than an array of eleven fields. The key is rebuilt on decode.
     */
    public function encode(): string
    {
        return serialize([$this->className, $this->name, $this->file, $this->line, $this->effects, $this->effectFree, $this->handles, $this->private, $this->abstract, $this->exemptFromRules]);
    }

    public static function decode(string $encoded): self
    {
        /** @var array{string|null, string, string|null, int|null, list<string>, list<string>, list<string>, bool, bool, list<string>} $data */
        $data = unserialize($encoded, ['allowed_classes' => false]);
        [$className, $name] = $data;

        return new self(
            $className === null ? MethodKey::forFunction($name) : MethodKey::forMethod($className, $name),
            ...$data,
        );
    }

    /**
     * Whether an EffectFree contract on this method binds the methods that
     * override or implement it.
     *
     * Private methods are invisible to subclasses, so a same-named method is
     * not an override. Constructors are exempt from substitutability (callers
     * always name the concrete class, and PHP does not check their
     * compatibility) unless declared abstract or on an interface, where PHP
     * does enforce the signature on every implementation.
     */
    public function passesContractToOverrides(): bool
    {
        if ($this->private) {
            return false;
        }

        return strtolower($this->name) !== '__construct' || $this->abstract;
    }

    public function displayName(): string
    {
        if ($this->className !== null) {
            return $this->className . '::' . $this->name . '()';
        }

        return $this->name . '()';
    }
}
