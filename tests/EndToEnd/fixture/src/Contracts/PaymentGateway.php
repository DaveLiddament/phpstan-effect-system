<?php

declare(strict_types=1);

namespace FixtureApp\Contracts;

interface PaymentGateway
{
    public function charge(): void;
}
