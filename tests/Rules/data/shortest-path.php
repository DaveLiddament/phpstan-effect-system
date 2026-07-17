<?php

declare(strict_types=1);

namespace EffectTest\ShortestPath;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Leaf
{
    #[Effect('slow')]
    public function slow(): void
    {
    }
}

class LongWayRound
{
    public function a(): void
    {
        (new LongWayRoundInner())->b();
    }
}

class LongWayRoundInner
{
    public function b(): void
    {
        (new Leaf())->slow();
    }
}

class Sink
{
    #[EffectFree('slow')]
    public function entry(): void
    {
        (new LongWayRound())->a();
        (new Leaf())->slow();
    }
}
