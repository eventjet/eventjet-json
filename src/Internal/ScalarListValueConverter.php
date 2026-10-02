<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;

use function array_is_list;
use function array_map;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final class ScalarListValueConverter
{
    /**
     * @param class-string $class
     * @return list<bool|float|int|string>|DecodeError|null
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): array|DecodeError|null {
        $itemType = ScalarListTypeResolver::resolve($field);

        if ($itemType === null) {
            return null;
        }

        $expectedType = sprintf('list<%s>', $itemType);

        if (!is_array($value) || !array_is_list($value)) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        $index = 0;
        $items = array_map(static function (mixed $item) use (
            $class,
            $field,
            &$index,
            $itemType,
        ): bool|float|int|string|DecodeError {
            $converted = self::convertItem($class, sprintf('%s[%d]', $field->getName(), $index), $itemType, $item);
            ++$index;

            return $converted;
        }, $value);
        $converted = [];

        foreach ($items as $convertedItem) {
            if ($convertedItem instanceof DecodeError) {
                return $convertedItem;
            }

            $converted[] = $convertedItem;
        }

        return $converted;
    }

    /**
     * @param class-string $class
     * @param 'bool'|'float'|'int'|'string' $type
     */
    private static function convertItem(
        string $class,
        string $path,
        string $type,
        mixed $value,
    ): bool|float|int|string|DecodeError {
        $converted = match ($type) {
            'bool' => is_bool($value) ? $value : null,
            'float' => self::convertFloat($value),
            'int' => is_int($value) ? $value : null,
            'string' => is_string($value) ? $value : null,
        };

        if ($converted === null) {
            return DecodeError::fieldTypeMismatch($class, $path, $type, $value);
        }

        return $converted;
    }

    private static function convertFloat(mixed $value): float|null
    {
        if (is_float($value)) {
            return $value;
        }

        return is_int($value) ? $value : null;
    }
}
