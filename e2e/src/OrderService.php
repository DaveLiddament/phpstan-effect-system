<?php

declare(strict_types=1);

namespace E2eApp;

class OrderService
{
    public function load(): void
    {
        (new ApiClient())->fetch();
    }

    public function count(): int
    {
        return 0;
    }
}
