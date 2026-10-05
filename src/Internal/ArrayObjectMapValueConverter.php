<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class ArrayObjectMapValueConverter
{
    /**
     * @param class-string $class
     * @return ArrayObject<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): ArrayObject|DecodeError {
        $converted = MapValueConverter::convert($class, $field, $value);

        return $converted instanceof DecodeError ? $converted : new ArrayObject($converted);
    }
}
