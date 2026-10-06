<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

use function array_key_exists;
use function array_keys;
use function assert;

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

        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $itemPath = FieldPath::key($path, (string) $key);
            $convertedValue = CollectionItemValueConverter::convert(
                $class,
                $itemPath,
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
