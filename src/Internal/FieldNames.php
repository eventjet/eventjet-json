<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function array_combine;
use function array_flip;
use function array_keys;
use function array_map;
use function count;

/** @internal */
final class FieldNames
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function resolve(ReflectionClass $class, array $properties): array|DecodeError
    {
        try {
            $names = self::discover($properties);
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error);
        }
        if ($names instanceof DecodeError) {
            return $names;
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            return InvalidFieldMapping::withoutJsonSerializable();
        }
        $collision = FieldNameCollisions::validate($class, $names);
        return $collision ?? $names;
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
     * @param list<ReflectionProperty> $properties
     * @return array<string, string>|DecodeError
     */
    private static function discover(array $properties): array|DecodeError
    {
        $names = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);
            $name = $property->getName();
            if (($property->getModifiers() & ReflectionProperty::IS_PUBLIC) === 0 || $property->isStatic()) {
                return InvalidFieldMapping::nonPublicProperty($name);
            }
            if (count($attributes) !== 1) {
                return InvalidFieldMapping::repeated($name);
            }
            $names[$name] = $attributes[0]->newInstance()->name;
        }
        return $names;
    }
}
