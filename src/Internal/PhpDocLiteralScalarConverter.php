<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use stdClass;

use function class_exists;
use function enum_exists;
use function get_debug_type;
use function implode;
use function is_float;
use function is_int;

/** @internal */
final class PhpDocLiteralScalarConverter
{
    /**
     * @param class-string $class
     * @param list<string>|null $names
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     */
    public static function convert(
        string $class,
        string $path,
        array|null $names,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        if ($names === null) {
            return $value;
        }
        $matches = self::matches($names, $value);
        return $matches
            ? self::restore($names, $value)
            : DecodeError::fieldTypeMismatch($class, $path, implode('|', $names), $value);
    }

    /**
     * @param list<string>|null $names
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     */
    private static function restore(array|null $names, mixed $value): array|bool|float|int|object|string|null
    {
        foreach ($names ?? [] as $name) {
            $literal = PhpDocLiteral::value($name);
            $matches = PhpDocLiteral::matches($name, $value);
            if (is_float($literal) && $matches) {
                return $literal;
            }
        }
        return $value;
    }

    /** @param list<string> $names */
    private static function matches(array $names, mixed $value): bool
    {
        foreach ($names as $name) {
            $matches = PhpDocLiteral::matches($name, $value);
            if (
                $matches
                || $name === get_debug_type($value)
                || $name === 'float' && is_int($value)
                || enum_exists($name)
            ) {
                return true;
            }
            if ($value instanceof stdClass && class_exists($name)) {
                return true;
            }
        }
        return false;
    }
}
