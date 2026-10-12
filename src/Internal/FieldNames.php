<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

use function array_combine;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_map;
use function array_push;

/**
 * Resolves the JSON names that #[Field] gives to the properties of a class, once per class.
 *
 * @internal
 */
final class FieldNames
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * Returns the JSON names of the renamed properties, keyed by property name. All other properties use their own
     * names in JSON.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function of(ReflectionClass $class): array|DecodeError
    {
        return self::$names[$class->getName()] ?? self::resolve($class);
    }

    /**
     * Re-keys values from JSON names to property names.
     *
     * @template V
     * @param array<array-key, V> $values
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @return array<array-key, V>
     */
    public static function toProperties(array $values, array $names): array
    {
        /** @var array<array-key, string> $properties */
        $properties = array_flip($names);
        $keys = array_map(static fn(int|string $name): int|string => $properties[$name] ?? $name, array_keys($values));
        return array_combine($keys, $values);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     */
    private static function resolve(ReflectionClass $class): array|DecodeError
    {
        $className = $class->getName();
        $properties = $class->getProperties();
        $annotated = array_filter(
            [...$properties, ...self::privateAncestorProperties($class)],
            static fn(ReflectionProperty $property): bool => $property->getAttributes(Field::class) !== [],
        );
        if ($annotated === []) {
            return $class->implementsInterface(JsonSerializable::class)
                ? DecodeError::jsonSerializableTarget($className)
                : (self::$names[$className] = []);
        }
        $names = FieldNameRules::resolve($class, $properties, $annotated);
        return $names instanceof DecodeError ? $names : (self::$names[$className] = $names);
    }

    /**
     * Returns the private properties of all ancestors, which reflection leaves out of the properties of the class.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return list<ReflectionProperty>
     */
    private static function privateAncestorProperties(ReflectionClass $class): array
    {
        $properties = [];
        $parent = $class->getParentClass();
        while ($parent !== false) {
            array_push($properties, ...$parent->getProperties(ReflectionProperty::IS_PRIVATE));
            $parent = $parent->getParentClass();
        }
        return $properties;
    }
}
