<?php

declare(strict_types=1);

namespace EffectTest\ConstructorContract;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class Entity
{
    #[EffectFree('slow')]
    public function __construct()
    {
    }
}

// Constructors are treated like any other override: the parent's contract
// covers the whole hierarchy.
class SlowEntity extends Entity
{
    public function __construct()
    {
        (new Db())->query();
    }
}

class FastEntity extends Entity
{
    public function __construct()
    {
    }
}
