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

class StaticDb
{
    #[Effect('slow')]
    public static function query(): void
    {
    }
}

#[Effect('slow')]
function slowFunction(): void
{
}

class StaticAndFunctionApi
{
    #[EffectFree('slow')]
    public function viaStaticMethod(): callable
    {
        return StaticDb::query(...);
    }

    #[EffectFree('slow')]
    public function viaFunction(): callable
    {
        return slowFunction(...);
    }
}
