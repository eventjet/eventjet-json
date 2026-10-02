<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_keys;
use function is_int;

/** @internal */
final class MapInputNormalizer
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|DecodeError|null
     */
    public static function normalize(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): array|DecodeError|null {
        $isNonEmptyArray = MapTypeResolver::isNonEmptyArray($field);
        $isArrayObject = MapTypeResolver::isArrayObject($field);

        if (!$isNonEmptyArray && !$isArrayObject) {
            return null;
        }

        if (!$value instanceof stdClass) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), 'JSON object', $value);
        }

        $values = (array) $value;
        $numericKey = self::numericKey($values);

        if ($numericKey !== null) {
            return MapDecodeError::numericKey($class, $field->getName(), $numericKey);
        }

        if ($isNonEmptyArray && $values === []) {
            return MapDecodeError::empty($class, $field->getName());
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
