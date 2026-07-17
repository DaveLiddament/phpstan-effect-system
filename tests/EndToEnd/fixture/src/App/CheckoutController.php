<?php

declare(strict_types=1);

namespace FixtureApp\App;

use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;
use FixtureApp\Contracts\PaymentGateway;

class CheckoutController
{
    #[EffectFree('slow')]
    public function checkout(PaymentGateway $gateway): void
    {
        $gateway->charge();
    }

    #[EffectFree('slow')]
    public function summary(): void
    {
        (new Helper())->format();
    }
}
