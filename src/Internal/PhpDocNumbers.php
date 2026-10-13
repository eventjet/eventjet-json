<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function count;

/**
 * Finds number literals that PHP and PHPStan can read differently, which make a declaration unsupported. See
 * {@see PhpDocLiteralNumber}.
 *
 * @internal
 */
final class PhpDocNumbers
{
    /** Returns the first unsupported number literal, shape key, or integer range bound in a type, or null. */
    public static function unsupported(PhpDocType $type): string|null
    {
        $unsupported = self::isUnsupported($type->name);
        if ($unsupported) {
            return $type->name;
        }
        $range = $type->name === 'int' && count($type->arguments) === 2;
        foreach ($type->arguments as $argument) {
            $bound = $range && PhpDocLiteralNumber::isNumber($argument->name);
            $invalid = $bound && !PhpDocLiteralNumber::isRangeBound($argument->name);
            $number = $invalid ? $argument->name : self::unsupported($argument);
            if ($number !== null) {
                return $number;
            }
        }
        foreach ($type->entries as $entry) {
            $key = $entry->key ?? '';
            $invalid = self::isUnsupported($key);
            $number = $invalid ? $key : self::unsupported($entry->type);
            if ($number !== null) {
                return $number;
            }
        }
        return null;
    }

    private static function isUnsupported(string $name): bool
    {
        return PhpDocLiteralNumber::isNumber($name) && PhpDocLiteralNumber::parse($name) === null;
    }
}
