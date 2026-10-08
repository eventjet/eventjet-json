<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function implode;
use function is_float;

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
        $matches = PhpDocLiteralField::matches($names, $value);
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
}
