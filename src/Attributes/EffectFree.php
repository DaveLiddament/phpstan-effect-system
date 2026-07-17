<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Attributes;

/**
 * Declares that this method or function must not transitively reach the named
 * effect (an effect sink).
 *
 * Valid on interface and abstract methods; the contract applies to all
 * implementations and overrides and cannot be dropped.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
final class EffectFree
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
