<?php

declare(strict_types=1);

namespace EffectTest\Stubs;

use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use EffectTest\StubVendor\Mailer;

class HttpWrapper
{
    public function get(): void
    {
        file_get_contents('https://example.com');
    }
}

class Service
{
    #[EffectFree('io')]
    public function fetch(): void
    {
        (new HttpWrapper())->get();
    }

    #[EffectFree('smtp')]
    public function notify(): void
    {
        (new Mailer())->send();
    }
}
