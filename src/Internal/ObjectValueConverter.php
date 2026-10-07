<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;

use function array_key_exists;

/** @internal */
final class ObjectValueConverter
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @param array<string, ListType|MapType|TupleType|FieldCollectionUnionType|null> $collections
     * @return array<array-key, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        ReflectionClass $class,
        array $values,
        array $collections,
        string $path,
    ): array|DecodeError {
        $className = $class->getName();
        $convertedValues = [];

        foreach (ConstructorParameters::resolve($class) as $parameter) {
            $field = $parameter->name;
            $fieldPath = FieldPath::field($path, $field);

            if (!array_key_exists($field, $values)) {
                continue;
            }

            /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
            $value = $values[$field];
            $collection = $collections[$field] ?? null;
            $converted = match (true) {
                $collection instanceof FieldCollectionUnionType => FieldCollectionUnionValueConverter::convert(
                    $className,
                    $fieldPath,
                    $collection,
                    $value,
                ),
                $collection !== null => CollectionValueConverter::convert($className, $fieldPath, $collection, $value),
                default => self::convertField($className, $parameter, $value, $fieldPath),
            };

            if ($converted instanceof DecodeError) {
                return $converted;
            }

            $convertedValues[$field] = $converted;
        }

        return $convertedValues;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    private static function convertField(
        string $class,
        ConstructorParameter $parameter,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        $type = $parameter->type;

        if ($type instanceof ReflectionNamedType) {
            return NamedFieldValueConverter::convert($class, $parameter->reflection, $type, $value, $path);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::convertUnion($class, $parameter->reflection, $type, $value, $path);
        }

        return $value;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    private static function convertUnion(
        string $class,
        ReflectionParameter $parameter,
        ReflectionUnionType $type,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        $converted = BackedEnumValueConverter::convert($class, $parameter, $value, $path);

        if ($converted !== null) {
            return $converted;
        }

        if ($value instanceof stdClass) {
            $converted = ConcreteClassUnionValueConverter::convert($class, $parameter, $type, $value, $path);

            if ($converted !== null) {
                return $converted;
            }
        }

        $matches = ValueTypeMatcher::matchesBuiltinUnion($value, $type);
        if (!$matches) {
            return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
        }

        return $value;
    }
}
