<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

final class DeclarationRecord
{
    /**
     * @param list<string> $effects
     * @param list<string> $effectFree
     * @param list<string> $handles
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
    ) {
    }

    /**
     * @param array{key: string, class: string|null, name: string, file: string|null, line: int|null, effects: list<string>, effectFree: list<string>, handles: list<string>, private: bool, abstract: bool} $data
     */
    public static function fromCollectedArray(array $data): self
    {
        return new self(
            $data['key'],
            $data['class'],
            $data['name'],
            $data['file'],
            $data['line'],
            $data['effects'],
            $data['effectFree'],
            $data['handles'],
            $data['private'],
            $data['abstract'],
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
