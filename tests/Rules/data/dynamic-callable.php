<?php

declare(strict_types=1);

namespace EffectTest\DynamicCallable;

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
    /**
     * Invoking a variable callable is not tracked (documented false negative);
     * the edge is instead recorded where the callable is created.
     */
    #[EffectFree('slow')]
    public function handle(callable $fn): void
    {
        $fn();
    }
}
