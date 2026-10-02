<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;
use UnitEnum;

use function array_key_exists;
use function array_keys;
use function assert;
use function enum_exists;
use function get_object_vars;
use function sprintf;

/** @internal */
final class BackedEnumMapValueConverter
{
    /**
     * @param class-string $class
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, UnitEnum>|DecodeError|null
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        array|stdClass $value,
    ): array|DecodeError|null {
        $valueType = CollectionTypeResolver::resolveMapValue($field);

        if ($valueType === null || !enum_exists($valueType)) {
            return null;
        }

        $enum = new ReflectionEnum($valueType);

        if (!$enum->isBacked()) {
            return DecodeError::nonBackedEnum($class, $valueType, $field->getName());
        }

        $values = $value instanceof stdClass ? get_object_vars($value) : $value;
        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');

            $convertedItem = BackedEnumValueConverter::convertValue(
                $class,
                sprintf('%s[%s]', $field->getName(), $key),
                $valueType,
                $values[$key],
            );

            if ($convertedItem instanceof DecodeError) {
                return $convertedItem;
            }

            $converted[$key] = $convertedItem;
        }

        return $converted;
    }
}
