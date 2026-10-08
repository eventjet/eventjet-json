<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use stdClass;

use function array_keys;
use function is_int;

/** @internal */
final class MapInputNormalizer
{
    /**
     * @param class-string $class
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     */
    public static function normalize(string $class, string $path, MapType $collection, mixed $value): array|DecodeError
    {
        if (!$value instanceof stdClass) {
            return DecodeError::fieldTypeMismatch($class, $path, 'JSON object', $value);
        }

        /** @var array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
        $values = (array) $value;

        if (!$collection->arrayObject && $values === []) {
            return MapDecodeError::empty($class, $path);
        }

        return self::stringKeys($class, $path, $values);
    }

    /**
     * @param class-string $class
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     */
    private static function stringKeys(string $class, string $path, array $values): array|DecodeError
    {
        foreach (array_keys($values) as $key) {
            if (is_int($key)) {
                return MapDecodeError::numericKey($class, $path, $key);
            }
        }

        /** @var array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values */
        return $values;
    }
}
