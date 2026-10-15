<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Encodes the call records of one call site as a single string. PHPStan keeps
 * every collected record in memory and in the result cache, and one string
 * costs far less than nested arrays: "caller\ncallee\ncallee...". A
 * dispatching callee is "key\tcalledClass\tmethod"; any other callee is its
 * key. Names cannot contain a newline or a tab.
 *
 * @phpstan-type Callee array{key: string, calledClass: string|null, method: string|null, dispatch: bool}
 */
final class CallRecord
{
    /**
     * @param list<Callee> $callees
     */
    public static function encode(string $caller, array $callees): string
    {
        $record = $caller;
        foreach ($callees as $callee) {
            $record .= "\n" . $callee['key'];
            if ($callee['dispatch'] && $callee['calledClass'] !== null && $callee['method'] !== null) {
                $record .= "\t" . $callee['calledClass'] . "\t" . $callee['method'];
            }
        }

        return $record;
    }
}
