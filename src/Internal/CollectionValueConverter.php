<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class CollectionValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|ArrayObject<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType $type,
        mixed $value,
    ): array|ArrayObject|DecodeError {
        if ($type instanceof ListType) {
            return ListValueConverter::convert($class, $field, $type, $value);
        }
        if ($type instanceof TupleType) {
            return TupleValueConverter::convert($class, $field, $type, $value);
        }
        $converted = MapValueConverter::convert($class, $field, $type, $value);
        return $converted instanceof DecodeError || !$type->arrayObject ? $converted : new ArrayObject($converted);
    }
}
