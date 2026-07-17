<?php

declare(strict_types=1);

namespace EffectTest\InheritedContract;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface Loader
{
    #[EffectFree('slow')]
    public function load(): void;
}

class DbLoader implements Loader
{
    public function load(): void
    {
        $this->slowQuery();
    }

    #[Effect('slow')]
    private function slowQuery(): void
    {
    }
}
