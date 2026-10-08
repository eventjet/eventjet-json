<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

/** @internal */
final class CollectionValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|ArrayObject<array-key, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        string $path,
        ListType|MapType|TupleType $type,
        mixed $value,
    ): array|ArrayObject|DecodeError {
        if ($type instanceof ListType) {
            return ListValueConverter::convert($class, $path, $type, $value);
        }
        if ($type instanceof TupleType) {
            return TupleValueConverter::convert($class, $path, $type, $value);
        }
        $converted = MapValueConverter::convert($class, $path, $type, $value);
        return $converted instanceof DecodeError || !$type->arrayObject ? $converted : new ArrayObject($converted);
    }
}
