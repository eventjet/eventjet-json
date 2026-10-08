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
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

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
        if (in_array($type, ['string', 'int', 'bool', 'float'], strict: true)) {
            return self::scalars($class, $path, $type, $values);
        }

        $converted = [];

        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $itemPath = FieldPath::key($path, $key);
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

    /**
     * @param class-string $class
     * @param 'string'|'int'|'bool'|'float' $type
     * @param array<string, mixed> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     */
    private static function scalars(string $class, string $path, string $type, array $values): array|DecodeError
    {
        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $valid = match ($type) {
                'string' => is_string($values[$key]),
                'int' => is_int($values[$key]),
                'bool' => is_bool($values[$key]),
                'float' => is_float($values[$key]) || is_int($values[$key]),
            };
            if (!$valid) {
                return DecodeError::fieldTypeMismatch($class, FieldPath::key($path, $key), $type, $values[$key]);
            }
            if ($type === 'float' && is_int($values[$key])) {
                $values[$key] = (float) $values[$key];
            }
        }
        return $values;
    }
}
