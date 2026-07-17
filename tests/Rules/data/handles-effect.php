<?php

declare(strict_types=1);

namespace EffectTest\HandlesEffect;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use DaveLiddament\PhpstanEffectSystem\Attributes\HandlesEffect;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class Cache
{
    #[HandlesEffect('slow')]
    public function remember(): void
    {
        (new Db())->query();
    }
}

class StillSlowCache
{
    #[Effect('slow')]
    #[HandlesEffect('slow')]
    public function warm(): void
    {
        (new Db())->query();
    }
}

class Api
{
    #[EffectFree('slow')]
    public function fast(): void
    {
        (new Cache())->remember();
    }

    #[EffectFree('slow')]
    public function stillSlow(): void
    {
        (new StillSlowCache())->warm();
    }
}
