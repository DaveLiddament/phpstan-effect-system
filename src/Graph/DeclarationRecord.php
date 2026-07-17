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
        public readonly bool $abstract,
    ) {
    }

    /**
     * @param array{key: string, class: string|null, name: string, file: string|null, line: int|null, effects: list<string>, effectFree: list<string>, handles: list<string>, abstract: bool} $data
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
            $data['abstract'],
        );
    }

    public function displayName(): string
    {
        if ($this->className !== null) {
            return $this->className . '::' . $this->name . '()';
        }

        return $this->name . '()';
    }
}
