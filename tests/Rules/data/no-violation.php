<?php

declare(strict_types=1);

namespace EffectTest\NoViolation;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

class Api
{
    #[EffectFree('slow')]
    public function handle(): int
    {
        return (new Calculator())->add(1, 2);
    }

    public function unconstrained(): void
    {
        (new Db())->query();
    }
}
