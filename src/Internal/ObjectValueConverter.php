<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
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
     * @param array<string, ListType|MapType|TupleType|null> $collections
     * @return array<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(ReflectionClass $class, array $values, array $collections): array|DecodeError
    {
        $className = $class->getName();
        $convertedValues = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $parameter->getName();

            if (!array_key_exists($field, $values)) {
                continue;
            }

            /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
            $value = $values[$field];
            $collection = $collections[$field] ?? null;
            $converted = $collection === null
                ? self::convertField($className, $parameter, $value)
                : CollectionValueConverter::convert($className, $parameter, $collection, $value);

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
        ReflectionParameter $parameter,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            return NamedFieldValueConverter::convert($class, $parameter, $type, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            $converted = BackedEnumValueConverter::convert($class, $parameter, $value);

            if ($converted !== null) {
                return $converted;
            }

            if ($value instanceof stdClass) {
                $converted = ConcreteClassUnionValueConverter::convert($class, $parameter, $type, $value);

                if ($converted !== null) {
                    return $converted;
                }
            }
        }

        return $value;
    }
}
