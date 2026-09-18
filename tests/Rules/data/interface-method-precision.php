<?php

declare(strict_types=1);

namespace EffectTest\InterfaceMethodPrecision;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface C
{
    public function slow(): void;

    public function fast(): void;
}

class ProdC implements C
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

namespace EffectTest\InterfaceMethodPrecision\Tests;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use EffectTest\InterfaceMethodPrecision\C;

// Slow in BOTH methods: if test doubles leaked into dispatch, fastRoute()
// would be reported too.
class FakeC implements C
{
    #[Effect('slow')]
    public function slow(): void
    {
    }

    #[Effect('slow')]
    public function fast(): void
    {
    }
}
