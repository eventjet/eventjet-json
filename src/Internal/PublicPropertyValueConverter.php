<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/** @internal */
final class PublicPropertyValueConverter
{
    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @param array{type: ReflectionNamedType|ReflectionUnionType, collection: ListType|MapType|TupleType|null, path: string} $field
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        array $field,
        mixed $value,
    ): array|DecodeError {
        $type = $field['type'];
        $path = $field['path'];
        if ($type instanceof ReflectionUnionType) {
            return PublicPropertyUnionValueConverter::convert($class, $property, $type, $value, $path);
        }

        return PublicPropertyNamedValueConverter::convert(
            $class,
            $property,
            $type,
            ['collection' => $field['collection'], 'path' => $path],
            $value,
        );
    }
}
