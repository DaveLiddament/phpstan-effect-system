<?php

declare(strict_types=1);

namespace EffectTest\InterfaceEffectDecl;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface RemoteApi
{
    #[Effect('http')]
    public function call(): void;
}

class StubApi implements RemoteApi
{
    public function call(): void
    {
    }
}

class Client
{
    #[EffectFree('http')]
    public function fetch(RemoteApi $api): void
    {
        $api->call();
    }
}
