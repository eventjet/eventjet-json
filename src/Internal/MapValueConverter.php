<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

use function array_key_exists;
use function array_keys;
use function assert;
use function in_array;

/** @internal */
final class MapValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(string $class, string $path, MapType $collection, mixed $value): array|DecodeError
    {
        $values = MapInputNormalizer::normalize($class, $path, $collection, $value);

        if ($values instanceof DecodeError) {
            return $values;
        }

        $type = $collection->valueType;
        return in_array($type, ['string', 'int', 'bool', 'float'], strict: true)
            ? ScalarMapValueConverter::convert($class, $path, $type, $values)
            : self::convertItems($class, $path, $type, $values);
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function convertItems(
        string $class,
        string $path,
        string|CollectionUnionType|NestedCollectionType $type,
        array $values,
    ): array|DecodeError {
        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $itemPath = FieldPath::key($path, $key);
            $convertedValue = CollectionItemValueConverter::convert($class, $itemPath, $type, $values[$key]);

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return $converted;
    }
}
