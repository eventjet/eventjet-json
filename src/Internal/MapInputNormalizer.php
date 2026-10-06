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
     * @return array<array-key, mixed>|DecodeError
     */
    public static function normalize(string $class, string $path, MapType $collection, mixed $value): array|DecodeError
    {
        if (!$value instanceof stdClass) {
            return DecodeError::fieldTypeMismatch($class, $path, 'JSON object', $value);
        }

        $values = (array) $value;
        $numericKey = self::numericKey($values);

        if ($numericKey !== null) {
            return MapDecodeError::numericKey($class, $path, $numericKey);
        }

        if (!$collection->arrayObject && $values === []) {
            return MapDecodeError::empty($class, $path);
        }

        return $values;
    }

    /** @param array<array-key, mixed> $values */
    private static function numericKey(array $values): int|null
    {
        foreach (array_keys($values) as $key) {
            if (is_int($key)) {
                return $key;
            }
        }

        return null;
    }
}
