<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Attributes;

/**
 * Exempts this method from an EffectFree contract imposed by an effects rule
 * in the configuration, e.g. an endpoint only ever requested by a background
 * job.
 *
 * Only contracts from effects rules can be exempted: a method's own or
 * inherited #[EffectFree] cannot be dropped. Unlike HandlesEffect, the effect
 * still propagates to callers.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ExemptFromEffectRule
{
    public function __construct(
        public readonly string $name,
        public readonly string $reason,
    ) {
    }
}
