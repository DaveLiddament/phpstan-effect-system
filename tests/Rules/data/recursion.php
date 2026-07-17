<?php

declare(strict_types=1);

namespace EffectTest\Recursion;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Walker
{
    #[EffectFree('slow')]
    public function entry(): void
    {
        $this->stepA(1);
    }

    public function stepA(int $depth): void
    {
        $this->stepB($depth + 1);
    }

    public function stepB(int $depth): void
    {
        if ($depth < 10) {
            $this->stepA($depth);
        }
        $this->slowLeaf();
        $this->stepB($depth + 1);
    }

    #[Effect('slow')]
    public function slowLeaf(): void
    {
    }
}
