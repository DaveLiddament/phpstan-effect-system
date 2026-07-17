<?php

declare(strict_types=1);

namespace EffectTest\ExcludedImplementations;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface Gateway
{
    public function send(): void;
}

class ProdGateway implements Gateway
{
    public function send(): void
    {
    }
}

interface Publisher
{
    public function publish(): void;
}

class ProdPublisher implements Publisher
{
    #[Effect('slow')]
    public function publish(): void
    {
    }
}

class App
{
    #[EffectFree('slow')]
    public function sendViaGateway(Gateway $gateway): void
    {
        $gateway->send();
    }

    #[EffectFree('slow')]
    public function publishViaPublisher(Publisher $publisher): void
    {
        $publisher->publish();
    }
}

namespace EffectTest\ExcludedImplementations\Tests;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use EffectTest\ExcludedImplementations\Gateway;

class FakeGateway implements Gateway
{
    #[Effect('slow')]
    public function send(): void
    {
    }
}
