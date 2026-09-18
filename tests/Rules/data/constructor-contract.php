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

// Not reported: `new SlowEntity()` always names the concrete class, so nobody
// constructs it believing they get Entity's constructor. PHP itself exempts
// constructors from compatibility checks for the same reason.
class SlowEntity extends Entity
{
    public function __construct()
    {
        (new Db())->query();
    }
}

// An interface constructor IS a promise PHP enforces on every implementation,
// so its contract is inherited.
interface Buildable
{
    #[EffectFree('slow')]
    public function __construct();
}

class SlowBuildable implements Buildable
{
    public function __construct()
    {
        (new Db())->query();
    }
}

// Likewise for an abstract constructor.
abstract class Template
{
    #[EffectFree('slow')]
    abstract public function __construct();
}

class SlowTemplate extends Template
{
    public function __construct()
    {
        (new Db())->query();
    }
}
