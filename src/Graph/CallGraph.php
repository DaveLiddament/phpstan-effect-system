<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

final class CallGraph
{
    /** @var array<string, array<string, true>> */
    private array $forward = [];

    /** @var array<string, array<string, true>> */
    private array $reverse = [];

    public function addEdge(string $from, string $to): void
    {
        $this->forward[$from][$to] = true;
        $this->reverse[$to][$from] = true;
    }

    /** @return list<string> */
    public function calleesOf(string $key): array
    {
        return array_keys($this->forward[$key] ?? []);
    }

    /** @return list<string> */
    public function callersOf(string $key): array
    {
        return array_keys($this->reverse[$key] ?? []);
    }
}
