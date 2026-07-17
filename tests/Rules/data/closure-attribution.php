<?php

declare(strict_types=1);

namespace EffectTest\ClosureAttribution;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class Files
{
    #[Effect('io')]
    public function write(): int
    {
        return 1;
    }
}

class Runner
{
    #[EffectFree('slow')]
    #[EffectFree('io')]
    public function run(): void
    {
        $closure = function (): void {
            (new Db())->query();
        };
        $closure();

        $arrow = fn (): int => (new Files())->write();
        $arrow();
    }
}
