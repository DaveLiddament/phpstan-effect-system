<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Whole-project class hierarchy index built from collected per-class records.
 * All class names are stored lowercase (PHP class names are case-insensitive).
 */
final class ClassHierarchy
{
    /** @var array<string, array{name: string, parents: list<string>, interfaces: list<string>}> classLower => record */
    private array $classes = [];

    /** @var array<string, list<string>> ancestorLower => subtype classLower list */
    private array $subtypes = [];

    /**
     * @param list<array{class: string, parents: list<string>, interfaces: list<string>, isInterface: bool, isAbstract: bool, isFinal: bool, isAnonymous: bool}> $records
     */
    public static function fromCollectedRecords(array $records): self
    {
        $hierarchy = new self();
        foreach ($records as $record) {
            $classLower = strtolower(ltrim($record['class'], '\\'));
            if (isset($hierarchy->classes[$classLower])) {
                continue;
            }

            $parents = array_map(
                static fn (string $name): string => strtolower(ltrim($name, '\\')),
                $record['parents'],
            );
            $interfaces = array_map(
                static fn (string $name): string => strtolower(ltrim($name, '\\')),
                $record['interfaces'],
            );

            $hierarchy->classes[$classLower] = ['name' => ltrim($record['class'], '\\'), 'parents' => $parents, 'interfaces' => $interfaces];
            foreach ([...$parents, ...$interfaces] as $ancestor) {
                $hierarchy->subtypes[$ancestor][] = $classLower;
            }
        }

        return $hierarchy;
    }

    /** @return array<string, string> every known class: classLower => display name, sorted for determinism */
    public function classNames(): array
    {
        $names = array_map(static fn (array $record): string => $record['name'], $this->classes);
        ksort($names, SORT_STRING);

        return $names;
    }

    /** @return list<string> transitive subtypes (lowercase), excluding the class itself */
    public function subtypesOf(string $classLower): array
    {
        return $this->subtypes[$classLower] ?? [];
    }

    /** @return list<string> parent class chain (lowercase), nearest first */
    public function parentsOf(string $classLower): array
    {
        return $this->classes[$classLower]['parents'] ?? [];
    }

    /** @return list<string> all ancestors: parent chain then interfaces (lowercase) */
    public function ancestorsOf(string $classLower): array
    {
        $record = $this->classes[$classLower] ?? null;
        if ($record === null) {
            return [];
        }

        return [...$record['parents'], ...$record['interfaces']];
    }

    /**
     * Resolves which declaration actually provides $methodLower for
     * $classLower: the class's own declaration, or the nearest parent's.
     */
    public function resolveImplementation(Declarations $declarations, string $classLower, string $methodLower): ?string
    {
        foreach ([$classLower, ...$this->parentsOf($classLower)] as $candidate) {
            $key = $declarations->methodKeyOfClass($candidate, $methodLower);
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }
}
