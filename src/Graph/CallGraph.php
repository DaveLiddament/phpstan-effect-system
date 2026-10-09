<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Stores reverse edges only: effect propagation and path finding walk from
 * callees to callers. Forward edges are derived on demand.
 *
 * A dynamic call can reach every implementation of its dispatch target. The
 * graph stores the call once, against the target, and the implementations
 * once per target, instead of one edge per caller and implementation.
 * callersOf() merges both, so to its users a dispatch call is still one
 * edge to each implementation.
 */
final class CallGraph
{
    /** @var array<string, array<string, true>> callee => callers */
    private array $reverse = [];

    /** @var array<string, array<string, true>> dispatch target => callers */
    private array $dispatchCallers = [];

    /** @var array<string, list<string>> dispatch target => implementations */
    private array $implementations = [];

    /** @var array<string, list<string>> implementation => dispatch targets that reach it */
    private array $targetsOf = [];

    /** @var array<string, array<string, true>>|null */
    private ?array $forward = null;

    public function addEdge(string $from, string $to): void
    {
        $this->reverse[$to][$from] = true;
        $this->forward = null;
    }

    /**
     * @param list<string> $implementations
     */
    public function addDispatchTarget(string $target, array $implementations): void
    {
        $this->implementations[$target] = $implementations;
        foreach ($implementations as $implementation) {
            $this->targetsOf[$implementation][] = $target;
        }
        $this->forward = null;
    }

    public function addDispatchEdge(string $from, string $target): void
    {
        $this->dispatchCallers[$target][$from] = true;
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
            foreach ($this->dispatchCallers as $target => $callers) {
                foreach ($callers as $from => $_) {
                    foreach ($this->implementations[$target] ?? [] as $implementation) {
                        $this->forward[$from][$implementation] = true;
                    }
                }
            }
        }

        return array_keys($this->forward[$key] ?? []);
    }

    /** @return list<string> */
    public function callersOf(string $key): array
    {
        $callers = $this->reverse[$key] ?? [];
        foreach ($this->targetsOf[$key] ?? [] as $target) {
            $callers += $this->dispatchCallers[$target] ?? [];
        }

        return array_keys($callers);
    }
}
