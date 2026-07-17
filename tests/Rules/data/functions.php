<?php

declare(strict_types=1);

namespace EffectTest\Functions;

use DaveLiddament\PhpstanEffectSystem\Attributes\Effect;
use DaveLiddament\PhpstanEffectSystem\Attributes\EffectFree;

#[Effect('io')]
function readData(): void
{
}

function middle(): void
{
    readData();
}

#[EffectFree('io')]
function entry(): void
{
    middle();
}
