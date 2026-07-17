<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Attributes;

/**
 * Declares that this method or function discharges the named effect: callees'
 * effects of this name do not propagate through it (a handler, e.g. a caching
 * wrapper around a slow call).
 *
 * An Effect of the same name declared on the same method still counts.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final class HandlesEffect
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
