<?php

declare(strict_types=1);

namespace EffectTest\FirstClassCallable;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class Api
{
    #[EffectFree('slow')]
    public function handle(): callable
    {
        return (new Db())->query(...);
    }
}
