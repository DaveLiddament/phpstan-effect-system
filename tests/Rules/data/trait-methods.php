<?php

declare(strict_types=1);

namespace EffectTest\TraitMethods;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

trait Guarded
{
    #[EffectFree('slow')]
    public function guarded(): void
    {
        $this->doWork();
    }
}

class FastService
{
    use Guarded;

    public function doWork(): void
    {
    }
}

class SlowService
{
    use Guarded;

    #[Effect('slow')]
    public function doWork(): void
    {
    }
}
