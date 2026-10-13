<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function implode;
use function sort;
use function sprintf;
use function var_export;

/**
 * Errors for unions whose members JSON cannot tell apart.
 *
 * @internal
 */
final class AmbiguousUnion
{
    /** @param list<string> $classes */
    public static function classes(string $field, array $classes): DecodeError
    {
        sort($classes);
        return self::error(sprintf(
            'Field %s uses multiple class types: %s. JSON does not identify which class to instantiate.',
            $field,
            implode(', ', $classes),
        ));
    }

    public static function shapes(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            $field,
        ));
    }

    public static function overlappingEnums(
        string $field,
        string $first,
        string $second,
        int|string $value,
    ): DecodeError {
        return self::error(sprintf(
            'Field %s uses multiple backed enums %s and %s with overlapping backing value %s. JSON cannot identify which enum case to instantiate.',
            $field,
            $first,
            $second,
            var_export($value, return: true),
        ));
    }

    public static function enumAndFloat(string $field, string $enum): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses backed enum %s together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
            $field,
            $enum,
        ));
    }

    public static function enumAndBackingType(string $field, string $enum, string $backingType): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses backed enum %s together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
            $field,
            $enum,
            $backingType,
        ));
    }

    public static function overlappingLiterals(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s has overlapping literal union members. JSON cannot identify which PHP type to restore.',
            $field,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
