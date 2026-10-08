<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;

use function is_bool;
use function is_int;
use function is_string;

/** @internal */
final class ScalarMapValueConverter
{
    /**
     * @param class-string $class
     * @param 'string'|'int'|'bool'|'float' $type
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    public static function convert(string $class, string $path, string $type, array $values): array|DecodeError
    {
        return match ($type) {
            'string' => self::strings($class, $path, $values),
            'int' => self::integers($class, $path, $values),
            'bool' => self::booleans($class, $path, $values),
            'float' => FloatMapValueConverter::convert($class, $path, $values),
        };
    }

    /**
     * @param class-string $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    private static function strings(string $class, string $path, array $values): array|DecodeError
    {
        foreach ($values as $key => $item) {
            if (!is_string($item)) {
                return DecodeError::fieldTypeMismatch($class, FieldPath::key($path, $key), 'string', $item);
            }
        }
        return $values;
    }

    /**
     * @param class-string $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    private static function integers(string $class, string $path, array $values): array|DecodeError
    {
        foreach ($values as $key => $item) {
            if (!is_int($item)) {
                return DecodeError::fieldTypeMismatch($class, FieldPath::key($path, $key), 'int', $item);
            }
        }
        return $values;
    }

    /**
     * @param class-string $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    private static function booleans(string $class, string $path, array $values): array|DecodeError
    {
        foreach ($values as $key => $item) {
            if (!is_bool($item)) {
                return DecodeError::fieldTypeMismatch($class, FieldPath::key($path, $key), 'bool', $item);
            }
        }
        return $values;
    }
}
