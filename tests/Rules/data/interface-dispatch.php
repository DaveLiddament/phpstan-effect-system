<?php

declare(strict_types=1);

namespace EffectTest\InterfaceDispatch;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

interface Notifier
{
    public function send(): void;
}

class EmailNotifier implements Notifier
{
    public function send(): void
    {
    }
}

class SmsNotifier implements Notifier
{
    #[Effect('slow')]
    public function send(): void
    {
    }
}

class Alerter
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    #[EffectFree('slow')]
    public function alert(): void
    {
        $this->notifier->send();
    }
}
