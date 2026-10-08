<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;

/** @internal */
final class ObjectValueConverter
{
    /** @var array<class-string, array<string, FieldValueConverter>> */
    private static array $fields = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @param array<string, ListType|MapType|TupleType|CollectionUnionType|null> $collections
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
            self::$fields[$className][$field] ??= new FieldValueConverter(
                $parameter->reflection,
                $collections[$field] ?? null,
            );
            $converted = self::$fields[$className][$field]->convert($className, $value, $fieldPath);

            if ($converted instanceof DecodeError) {
                return $converted;
            }

            $convertedValues[$field] = $converted;
        }

        return $convertedValues;
    }
}
