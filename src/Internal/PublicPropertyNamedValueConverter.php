<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
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
     * @return array{property: ReflectionProperty, value: mixed}|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionProperty $property,
        ReflectionNamedType $type,
        ListType|MapType|TupleType|null $collection,
        mixed $value,
    ): array|DecodeError {
        $typeName = FieldTypeNameResolver::resolve($property, $type);
        $error = self::validateBuiltin($class, $property, $type, $value);
        if ($error !== null) {
            return $error;
        }

        $converted = match (true) {
            $collection !== null => CollectionValueConverter::convert($class, $property, $collection, $value),
            enum_exists($typeName) => BackedEnumValueConverter::convert($class, $property, $value) ?? $value,
            class_exists($typeName) => ConcreteClassValueConverter::convert($class, $property, $typeName, $value),
            default => $value,
        };

        return $converted instanceof DecodeError ? $converted : ['property' => $property, 'value' => $converted];
    }

    /** @param class-string $class */
    private static function validateBuiltin(
        string $class,
        ReflectionProperty $property,
        ReflectionNamedType $type,
        mixed $value,
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
        return DecodeError::fieldTypeMismatch($class, $property->getName(), $expectedType, $value);
    }
}
