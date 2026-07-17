<?php

declare(strict_types=1);

namespace DaveLiddament\PhpstanEffectSystem\Graph;

/**
 * Canonical graph-node keys. PHP class, method and function names are
 * case-insensitive, so both parts are lowercased (same normalization PHPStan
 * uses internally). Methods always contain '::', functions always end in '()'
 * without '::', so the two key spaces cannot collide.
 */
final class MethodKey
{
    public static function forMethod(string $className, string $methodName): string
    {
        return strtolower(ltrim($className, '\\')) . '::' . strtolower($methodName);
    }

    public static function forFunction(string $functionName): string
    {
        return strtolower(ltrim($functionName, '\\')) . '()';
    }
}
