<?php

declare(strict_types=1);

namespace FixtureApp\Infra;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;

class SlowHttp
{
    #[Effect('slow')]
    public function post(): void
    {
    }
}
