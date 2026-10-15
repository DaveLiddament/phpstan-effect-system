<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Tests\Graph;

use DaveLiddament\PhpstanEffectSystem\Graph\CallGraph;
use DaveLiddament\PhpstanEffectSystem\Graph\PathFinder;
use PHPUnit\Framework\TestCase;

final class PathFinderTest extends TestCase
{
    /**
     * Pins the tie-break between shortest paths of equal length: the path
     * takes the smallest key (in sort order) at every step. Edges are added
     * in reverse order, so insertion order alone gives the other path.
     */
    public function testEqualLengthPathsResolveToTheSmallestKeyAtEachStep(): void
    {
        $graph = new CallGraph();
        $graph->addEdge('app\c::run', 'app\d::run');
        $graph->addEdge('app\b::run', 'app\e::run');
        $graph->addEdge('app\d::run', 'io()');
        $graph->addEdge('app\e::run', 'io()');
        $graph->addEdge('app\a::run', 'app\c::run');
        $graph->addEdge('app\a::run', 'app\b::run');

        $effects = [];
        foreach (['app\a::run', 'app\b::run', 'app\c::run', 'app\d::run', 'app\e::run', 'io()'] as $key) {
            $effects[$key] = ['io' => true];
        }
        $declared = ['io()' => ['io' => true]];

        self::assertSame(
            ['app\a::run', 'app\b::run', 'app\e::run', 'io()'],
            (new PathFinder())->findPath($graph, 'app\a::run', 'io', $effects, $declared),
        );
    }

    /**
     * A shorter path wins over a path through smaller keys.
     */
    public function testShorterPathWinsOverSmallerKeys(): void
    {
        $graph = new CallGraph();
        $graph->addEdge('app\a::run', 'app\b::run');
        $graph->addEdge('app\b::run', 'app\c::run');
        $graph->addEdge('app\c::run', 'io()');
        $graph->addEdge('app\a::run', 'app\z::run');
        $graph->addEdge('app\z::run', 'io()');

        $effects = [];
        foreach (['app\a::run', 'app\b::run', 'app\c::run', 'app\z::run', 'io()'] as $key) {
            $effects[$key] = ['io' => true];
        }
        $declared = ['io()' => ['io' => true]];

        self::assertSame(
            ['app\a::run', 'app\z::run', 'io()'],
            (new PathFinder())->findPath($graph, 'app\a::run', 'io', $effects, $declared),
        );
    }
}
