<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

use SplQueue;

/**
 * Reconstructs a shortest call path from a sink to a method that declares the
 * effect, walking forward edges restricted to effect-carrying nodes.
 *
 * One reverse BFS per effect computes each node's distance to the nearest
 * declaring method; a path then follows, from each key, the smallest
 * successor (in sort order) one step closer. That is the path the
 * per-sink forward BFS with sorted successors finds, without re-walking the
 * graph for every sink. An instance assumes the same graph, effects and
 * declared sets on every call.
 */
final class PathFinder
{
    /** @var array<string, array<string, string|null>> effect => key => next key on a shortest path (null at a declaring key) */
    private array $nextHops = [];

    /**
     * @param array<string, array<string, true>> $effects reachable effects per key
     * @param array<string, array<string, true>> $declared declared effects per key
     * @return list<string> keys from the sink to the declaring source (inclusive)
     */
    public function findPath(CallGraph $graph, string $sink, string $effect, array $effects, array $declared): array
    {
        $nextHops = $this->nextHops[$effect] ??= $this->computeNextHops($graph, $effect, $effects, $declared);
        if (!array_key_exists($sink, $nextHops)) {
            return [$sink];
        }

        $path = [$sink];
        for ($current = $nextHops[$sink]; $current !== null; $current = $nextHops[$current]) {
            $path[] = $current;
        }

        return $path;
    }

    /**
     * Reverse BFS from every declaring key. A caller one level further out
     * keeps the smallest (in sort order) of its callees on the previous level.
     *
     * @param array<string, array<string, true>> $effects
     * @param array<string, array<string, true>> $declared
     * @return array<string, string|null>
     */
    private function computeNextHops(CallGraph $graph, string $effect, array $effects, array $declared): array
    {
        $distances = [];
        $nextHops = [];
        /** @var SplQueue<string> $queue */
        $queue = new SplQueue();
        foreach ($declared as $key => $set) {
            if (!isset($set[$effect])) {
                continue;
            }
            $distances[$key] = 0;
            $nextHops[$key] = null;
            $queue->enqueue($key);
        }

        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            $callerDistance = $distances[$node] + 1;
            foreach ($graph->callersOf($node) as $caller) {
                if (!isset($distances[$caller])) {
                    if (!isset($effects[$caller][$effect])) {
                        continue;
                    }
                    $distances[$caller] = $callerDistance;
                    $nextHops[$caller] = $node;
                    $queue->enqueue($caller);
                    continue;
                }
                if ($distances[$caller] !== $callerDistance || strcmp($node, (string) $nextHops[$caller]) >= 0) {
                    continue;
                }
                $nextHops[$caller] = $node;
            }
        }

        return $nextHops;
    }
}
