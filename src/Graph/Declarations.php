<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Index of every method/function declaration gathered by the collectors.
 */
final class Declarations
{
    /** @var array<string, DeclarationRecord> */
    private array $records = [];

    /** @var array<string, array<string, string>> class (lowercase) => method (lowercase) => key */
    private array $byClass = [];

    public function add(DeclarationRecord $record): void
    {
        // Duplicate keys are possible (e.g. conditionally declared functions):
        // first record wins, deterministically.
        if (isset($this->records[$record->key])) {
            return;
        }

        $this->records[$record->key] = $record;
        if ($record->className !== null) {
            $this->byClass[strtolower($record->className)][strtolower($record->name)] = $record->key;
        }
    }

    public function get(string $key): ?DeclarationRecord
    {
        return $this->records[$key] ?? null;
    }

    /** @return array<string, DeclarationRecord> keyed by graph key */
    public function all(): array
    {
        return $this->records;
    }

    public function methodKeyOfClass(string $classLower, string $methodLower): ?string
    {
        return $this->byClass[$classLower][$methodLower] ?? null;
    }

    public function displayName(string $key): string
    {
        $record = $this->records[$key] ?? null;
        if ($record !== null) {
            return $record->displayName();
        }

        return $key;
    }
}
