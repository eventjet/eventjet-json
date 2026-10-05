<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;

use function class_exists;
use function enum_exists;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final class CollectionItemValueConverter
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        string $path,
        string $type,
        mixed $value,
    ): bool|float|int|object|string {
        if (enum_exists($type)) {
            return BackedEnumValueConverter::convertValue($class, $path, $type, $value);
        }

        if (class_exists($type)) {
            return ConcreteClassValueConverter::convertCollectionItem($class, $path, $type, $value);
        }

        $converted = match ($type) {
            'bool' => is_bool($value) ? $value : null,
            'float' => self::convertFloat($value),
            'int' => is_int($value) ? $value : null,
            'string' => is_string($value) ? $value : null,
            default => null,
        };

        if ($converted === null) {
            return DecodeError::fieldTypeMismatch($class, $path, $type, $value);
        }

        return $converted;
    }

    private static function convertFloat(mixed $value): float|null
    {
        if (is_float($value)) {
            return $value;
        }

        return is_int($value) ? $value : null;
    }
}
