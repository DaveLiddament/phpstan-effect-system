<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

use SplQueue;

/**
 * Reconstructs a shortest call path from a sink to a method that declares the
 * effect, walking forward edges restricted to effect-carrying nodes.
 */
final class PathFinder
{
    /**
     * @param array<string, array<string, true>> $effects reachable effects per key
     * @param array<string, array<string, true>> $declared declared effects per key
     * @return list<string> keys from the sink to the declaring source (inclusive)
     */
    public function findPath(CallGraph $graph, string $sink, string $effect, array $effects, array $declared): array
    {
        /** @var array<string, string|null> $parents */
        $parents = [$sink => null];

        /** @var SplQueue<string> $queue */
        $queue = new SplQueue();
        $queue->enqueue($sink);

        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();

            if (isset($declared[$node][$effect])) {
                $path = [];
                for ($current = $node; $current !== null; $current = $parents[$current]) {
                    $path[] = $current;
                }

                return array_reverse($path);
            }

            $successors = $graph->calleesOf($node);
            sort($successors, SORT_STRING);
            foreach ($successors as $successor) {
                if (array_key_exists($successor, $parents)) {
                    continue;
                }
                if (!isset($effects[$successor][$effect])) {
                    continue;
                }
                $parents[$successor] = $node;
                $queue->enqueue($successor);
            }
        }

        return [$sink];
    }
}
