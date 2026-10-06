<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function array_keys;
use function assert;
use function sprintf;

/** @internal */
final class MapValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        MapType $collection,
        mixed $value,
    ): array|DecodeError {
        $values = MapInputNormalizer::normalize($class, $field, $collection, $value);

        if ($values instanceof DecodeError) {
            return $values;
        }

        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $path = sprintf('%s[%s]', $field->getName(), $key);
            $convertedValue = CollectionItemValueConverter::convert(
                $class,
                $path,
                $collection->valueType,
                $values[$key],
            );

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return $converted;
    }
}
