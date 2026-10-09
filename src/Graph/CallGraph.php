<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Stores reverse edges only: effect propagation and path finding walk from
 * callees to callers. Forward edges are derived on demand.
 */
final class CallGraph
{
    /** @var array<string, array<string, true>> */
    private array $reverse = [];

    /** @var array<string, array<string, true>>|null */
    private ?array $forward = null;

    public function addEdge(string $from, string $to): void
    {
        $this->reverse[$to][$from] = true;
        $this->forward = null;
    }

    /** @return list<string> */
    public function calleesOf(string $key): array
    {
        if ($this->forward === null) {
            $this->forward = [];
            foreach ($this->reverse as $to => $callers) {
                foreach ($callers as $from => $_) {
                    $this->forward[$from][$to] = true;
                }
            }
        }

        return array_keys($this->forward[$key] ?? []);
    }

    /** @return list<string> */
    public function callersOf(string $key): array
    {
        return array_keys($this->reverse[$key] ?? []);
    }
}
