<?php

declare(strict_types=1);

namespace EffectTest\RepeatableAttributes;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Gateway
{
    #[Effect('slow')]
    #[Effect('http')]
    public function send(): void
    {
    }
}

class Worker
{
    #[EffectFree('http')]
    public function process(): void
    {
        (new Gateway())->send();
    }

    #[EffectFree('slow')]
    #[EffectFree('http')]
    public function both(): void
    {
        (new Gateway())->send();
    }
}
