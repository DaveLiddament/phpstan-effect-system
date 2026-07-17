<?php

declare(strict_types=1);

namespace EffectTest\AllowedEffects;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use DaveLiddament\PhpstanEffectSystem\Attributes\HandlesEffect;

class Repo
{
    #[Effect('slwo')]
    public function load(): void
    {
    }

    #[EffectFree('database')]
    public function find(): void
    {
    }

    #[HandlesEffect('slow')]
    public function cached(): void
    {
    }
}
