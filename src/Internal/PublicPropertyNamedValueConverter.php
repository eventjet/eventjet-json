<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

use function class_exists;
use function enum_exists;

/** @internal */
final class PublicPropertyNamedValueConverter
{
    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @param array{collection: ListType|MapType|TupleType|null, path: string, typeName: string} $field
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        ReflectionNamedType $type,
        array $field,
        mixed $value,
    ): array|DecodeError {
        $collection = $field['collection'];
        $path = $field['path'];
        $typeName = $field['typeName'];
        $error = self::validateBuiltin($class, $type, $value, $path);
        if ($error !== null) {
            return $error;
        }

        $converted = match (true) {
            $collection !== null => CollectionValueConverter::convert($class, $path, $collection, $value),
            $type->isBuiltin() => $value,
            enum_exists($typeName) => BackedEnumValueConverter::convert($class, $property, $value, $path) ?? $value,
            class_exists($typeName) => ConcreteClassValueConverter::convert(
                $class,
                $property,
                $typeName,
                $value,
                $path,
            ),
            default => $value,
        };

        return $converted instanceof DecodeError ? $converted : ['property' => $property, 'value' => $converted];
    }

    /** @param class-string $class */
    private static function validateBuiltin(
        string $class,
        ReflectionNamedType $type,
        mixed $value,
        string $path,
    ): DecodeError|null {
        if (!$type->isBuiltin()) {
            return null;
        }
        $matches = ValueTypeMatcher::matches($value, $type);
        if ($matches) {
            return null;
        }
        $expectedType = $type->getName();
        if ($type->allowsNull() && $expectedType !== 'null') {
            $expectedType .= '|null';
        }
        return DecodeError::fieldTypeMismatch($class, $path, $expectedType, $value);
    }
}
