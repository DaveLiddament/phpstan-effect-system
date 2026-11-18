<?php

declare(strict_types=1);

namespace E2eApp\Controller;

use E2eApp\OrderService;

class OrderController
{
    // Violation from the pattern rule in phpstan.neon.
    public function showAction(): void
    {
        (new OrderService())->load();
    }

    // Covered by the same rule, but never reaches 'slow'.
    public function countAction(): int
    {
        return (new OrderService())->count();
    }
}
