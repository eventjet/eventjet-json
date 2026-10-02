<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_key_exists;
use function array_keys;
use function assert;
use function get_object_vars;
use function sprintf;

/** @internal */
final class ConcreteClassMapValueConverter
{
    /**
     * @param class-string $class
     * @param class-string $type
     * @param array<array-key, mixed>|stdClass $value
     * @return array<array-key, object>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
        array|stdClass $value,
    ): array|DecodeError {
        $values = $value instanceof stdClass ? get_object_vars($value) : $value;
        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = ConcreteClassValueConverter::convertCollectionItem($class, $path, $type, $values[$key]);

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return $converted;
    }
}
