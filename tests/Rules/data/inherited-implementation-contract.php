<?php

declare(strict_types=1);

namespace EffectTest\InheritedImplementationContract;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface Runner
{
    #[EffectFree('slow')]
    public function run(): void;
}

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

// Does not implement Runner itself...
class Base
{
    public function run(): void
    {
        (new Db())->query();
    }
}

// ...but its run() is what satisfies Runner::run() here.
class Child extends Base implements Runner
{
}

// Only ever paired with Runner by a test double, so no contract applies.
class Unrelated
{
    public function run(): void
    {
        (new Db())->query();
    }
}

namespace EffectTest\InheritedImplementationContract\Tests;

use EffectTest\InheritedImplementationContract\Runner;
use EffectTest\InheritedImplementationContract\Unrelated;

class FakeRunner extends Unrelated implements Runner
{
}
