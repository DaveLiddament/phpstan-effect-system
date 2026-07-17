<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Attributes;

/**
 * Declares that this method or function HAS the named effect (an effect source).
 *
 * Effects propagate transitively to all callers; they are only declared at the
 * source, never on intermediate callers.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final class Effect
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
