<?php

declare(strict_types=1);

namespace EffectTest\PrivateMethodContract;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

class Db
{
    #[Effect('slow')]
    public function query(): void
    {
    }
}

class ParentService
{
    #[EffectFree('slow')]
    private function helper(): void
    {
    }

    #[EffectFree('slow')]
    protected function hook(): void
    {
    }
}

class ChildService extends ParentService
{
    // Not an override: private methods are invisible to subclasses, so the
    // parent's contract does not apply.
    private function helper(): void
    {
        (new Db())->query();
    }

    // A real override: the contract is inherited.
    protected function hook(): void
    {
        (new Db())->query();
    }
}
