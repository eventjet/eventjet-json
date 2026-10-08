<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final class ScalarListValueConverter
{
    /**
     * @param class-string $class
     * @param 'string'|'int'|'bool'|'float' $itemType
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    public static function convert(string $class, string $path, string $itemType, array $value): array|DecodeError
    {
        return match ($itemType) {
            'string' => self::strings($class, $path, $value),
            'int' => self::integers($class, $path, $value),
            'bool' => self::booleans($class, $path, $value),
            'float' => FloatListValueConverter::convert($class, $path, $value),
        };
    }

    /**
     * @param class-string $class
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    private static function strings(string $class, string $path, array $value): array|DecodeError
    {
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if (!is_string($item)) {
                return DecodeError::fieldTypeMismatch($class, sprintf('%s[%d]', $path, $index), 'string', $item);
            }
        }
        return $value;
    }

    /**
     * @param class-string $class
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    private static function integers(string $class, string $path, array $value): array|DecodeError
    {
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if (!is_int($item)) {
                return DecodeError::fieldTypeMismatch($class, sprintf('%s[%d]', $path, $index), 'int', $item);
            }
        }
        return $value;
    }

    /**
     * @param class-string $class
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    private static function booleans(string $class, string $path, array $value): array|DecodeError
    {
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if (!is_bool($item)) {
                return DecodeError::fieldTypeMismatch($class, sprintf('%s[%d]', $path, $index), 'bool', $item);
            }
        }
        return $value;
    }
}
