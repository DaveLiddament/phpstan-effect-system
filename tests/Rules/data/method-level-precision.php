<?php

declare(strict_types=1);

namespace EffectTest\MethodLevelPrecision;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class C
{
    #[Effect('slow')]
    public function slow(): void
    {
    }

    public function fast(): void
    {
    }
}

class B
{
    public function __construct(
        private C $c,
    ) {
    }

    public function viaSlow(): void
    {
        $this->c->slow();
    }

    public function viaFast(): void
    {
        $this->c->fast();
    }
}

class A
{
    public function __construct(
        private B $b,
    ) {
    }

    #[EffectFree('slow')]
    public function slowRoute(): void
    {
        $this->b->viaSlow();
    }

    #[EffectFree('slow')]
    public function fastRoute(): void
    {
        $this->b->viaFast();
    }
}
