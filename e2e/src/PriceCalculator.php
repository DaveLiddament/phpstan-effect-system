<?php

declare(strict_types=1);

namespace E2eApp;

use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class PriceCalculator
{
    // Violation from the attribute.
    #[EffectFree('slow')]
    public function total(): void
    {
        (new OrderService())->load();
    }
}
