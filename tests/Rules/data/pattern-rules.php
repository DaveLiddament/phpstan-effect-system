<?php

declare(strict_types=1);

namespace EffectTest\PatternRules\Controller;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class HomeController
{
    public function indexAction(): void
    {
        (new Db())->query();
    }

    public function helper(): void
    {
        (new Db())->query();
    }
}
