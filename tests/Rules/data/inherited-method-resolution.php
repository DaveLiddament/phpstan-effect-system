<?php

declare(strict_types=1);

namespace EffectTest\InheritedMethodResolution;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class BaseRepo
{
    public function load(): void
    {
    }
}

class CachedRepo extends BaseRepo
{
    #[Effect('slow')]
    public function load(): void
    {
    }
}

class GrandChild extends CachedRepo
{
}

class Consumer
{
    #[EffectFree('slow')]
    public function viaParentType(BaseRepo $repo): void
    {
        $repo->load();
    }

    #[EffectFree('slow')]
    public function viaGrandChild(GrandChild $repo): void
    {
        $repo->load();
    }
}
