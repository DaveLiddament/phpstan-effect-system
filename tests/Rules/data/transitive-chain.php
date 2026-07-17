<?php

declare(strict_types=1);

namespace EffectTest\TransitiveChain;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class ApiClient
{
    #[Effect('slow')]
    public function fetch(): void
    {
    }
}

class OrderService
{
    public function load(): void
    {
        (new ApiClient())->fetch();
    }
}

class OrderRepository
{
    public function findAll(): void
    {
        (new OrderService())->load();
    }
}

class HomeController
{
    #[EffectFree('slow')]
    public function indexAction(): void
    {
        (new OrderRepository())->findAll();
    }
}
