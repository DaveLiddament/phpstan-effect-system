<?php

declare(strict_types=1);

namespace EffectTest\Contradiction;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface Api
{
    #[EffectFree('slow')]
    public function get(): void;
}

class BadApi implements Api
{
    #[Effect('slow')]
    public function get(): void
    {
    }
}

class SelfContradiction
{
    #[Effect('io')]
    #[EffectFree('io')]
    public function both(): void
    {
    }
}
