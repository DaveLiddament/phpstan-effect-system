<?php

declare(strict_types=1);

namespace EffectTest\StubVendor;

/**
 * Stands in for an unanalysed vendor class: it is autoloadable (classmap) but
 * never passed to analyse(), so no declaration record exists for it and its
 * effects must come from the stubs configuration.
 */
class Mailer
{
    public function send(): void
    {
    }
}
