<?php

declare(strict_types=1);

namespace EffectTest\LateStaticBinding;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Base
{
    final public function __construct()
    {
        $this->init();
    }

    protected function init(): void
    {
    }

    public static function make(): static
    {
        return new static();
    }

    public static function ping(): void
    {
    }
}

class SlowChild extends Base
{
    #[Effect('slow')]
    protected function init(): void
    {
    }

    #[Effect('slow')]
    public static function ping(): void
    {
    }
}

class Plain
{
    #[Effect('slow')]
    public function __construct()
    {
    }
}

class Consumer
{
    #[EffectFree('slow')]
    public function staticCallOnObject(Base $base): void
    {
        $base::ping();
    }

    /**
     * @param class-string<Base> $class
     */
    #[EffectFree('slow')]
    public function staticCallOnClassString(string $class): void
    {
        $class::ping();
    }

    /**
     * @param class-string<Plain> $class
     */
    #[EffectFree('slow')]
    public function newOnClassString(string $class): void
    {
        new $class();
    }

    #[EffectFree('slow')]
    public function explicitClassIsNotLateBound(): void
    {
        Base::ping();
    }
}

// No constructor of its own: new static() must still reach subclass constructors.
class Factory
{
    public static function create(): static
    {
        return new static();
    }
}

class SlowFactory extends Factory
{
    #[Effect('slow')]
    public function __construct()
    {
    }
}

class FactoryConsumer
{
    #[EffectFree('slow')]
    public function viaNewStatic(): void
    {
        Factory::create();
    }
}
