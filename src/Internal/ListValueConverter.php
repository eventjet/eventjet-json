<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_is_list;
use function array_key_exists;
use function class_exists;
use function enum_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final class ListValueConverter
{
    /**
     * @param class-string $class
     * @return list<bool|float|int|object|string>|DecodeError|null
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): array|DecodeError|null {
        $itemType = ListItemTypeResolver::resolve($field);

        if ($itemType === null) {
            return null;
        }

        if (enum_exists($itemType)) {
            $enum = new ReflectionEnum($itemType);

            if (!$enum->isBacked()) {
                return DecodeError::nonBackedEnum($class, $itemType, $field->getName());
            }
        }

        $expectedType = sprintf('list<%s>', $itemType);

        if (!is_array($value) || !array_is_list($value)) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        $converted = [];
        $index = 0;

        while (array_key_exists($index, $value)) {
            $convertedItem = self::convertItem(
                $class,
                sprintf('%s[%d]', $field->getName(), $index),
                $itemType,
                $value[$index],
            );

            if ($convertedItem instanceof DecodeError) {
                return $convertedItem;
            }

            $converted[] = $convertedItem;
            ++$index;
        }

        return $converted;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function convertItem(
        string $class,
        string $path,
        string $type,
        mixed $value,
    ): bool|float|int|object|string {
        if (enum_exists($type)) {
            return BackedEnumValueConverter::convertValue($class, $path, $type, $value);
        }

        if (class_exists($type)) {
            return ConcreteClassValueConverter::convertListItem($class, $path, $type, $value);
        }

        $converted = match ($type) {
            'bool' => is_bool($value) ? $value : null,
            'float' => self::convertFloat($value),
            'int' => is_int($value) ? $value : null,
            'string' => is_string($value) ? $value : null,
            default => null,
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
