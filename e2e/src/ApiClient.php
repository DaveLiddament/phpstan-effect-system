<?php

declare(strict_types=1);

namespace E2eApp;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;

class ApiClient
{
    #[Effect('slow')]
    public function fetch(): void
    {
    }
}
