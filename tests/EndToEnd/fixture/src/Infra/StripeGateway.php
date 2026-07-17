<?php

declare(strict_types=1);

namespace FixtureApp\Infra;

use FixtureApp\Contracts\PaymentGateway;

class StripeGateway implements PaymentGateway
{
    public function charge(): void
    {
        (new SlowHttp())->post();
    }
}
