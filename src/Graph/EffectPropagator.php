<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

use SplQueue;

/**
 * Computes transitive effect reachability: worklist fixpoint over reverse call
 * edges. Effect sets only grow and are bounded by the effect-name universe, so
 * the loop terminates through any recursion or mutual recursion.
 */
final class EffectPropagator
{
    /**
     * @param array<string, array<string, true>> $declared key => set of declared effect names
     * @param array<string, array<string, true>> $handles key => set of handled effect names
     * @return array<string, array<string, true>> key => set of reachable effect names
     */
    public function propagate(CallGraph $graph, array $declared, array $handles): array
    {
        $effects = $declared;

        /** @var SplQueue<string> $queue */
        $queue = new SplQueue();
        $queued = [];
        foreach ($declared as $key => $set) {
            if ($set === []) {
                continue;
            }
            $queue->enqueue($key);
            $queued[$key] = true;
        }

        while (!$queue->isEmpty()) {
            $key = $queue->dequeue();
            unset($queued[$key]);
            $current = $effects[$key] ?? [];

            foreach ($graph->callersOf($key) as $caller) {
                // HandlesEffect masks propagated-in effects only; the caller's
                // own declared effects were seeded directly and are never masked.
                $incoming = array_diff_key($current, $handles[$caller] ?? []);
                $new = array_diff_key($incoming, $effects[$caller] ?? []);
                if ($new === []) {
                    continue;
                }

                $effects[$caller] = ($effects[$caller] ?? []) + $new;
                if (isset($queued[$caller])) {
                    continue;
                }
                $queue->enqueue($caller);
                $queued[$caller] = true;
            }
        }

        return $effects;
    }
}
