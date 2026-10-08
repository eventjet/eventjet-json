<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;

use function is_float;
use function is_int;

/** @internal */
final class FloatMapValueConverter
{
    /**
     * @param class-string $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    public static function convert(string $class, string $path, array $values): array|DecodeError
    {
        foreach ($values as $key => $item) {
            if (!is_float($item) && !is_int($item)) {
                return DecodeError::fieldTypeMismatch($class, FieldPath::key($path, $key), 'float', $item);
            }
            if (is_int($item)) {
                $values[$key] = (float) $item;
            }
        }
        return $values;
    }
}
