<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use ReflectionClass;
use ReflectionProperty;

/**
 * Finds the public instance properties declared as `JsonValue|null`. json_encode() would write null for such a
 * property, which decodes as a JsonValue that holds null, so a serializer must leave it out instead. Like #[Field], such
 * a property therefore requires JsonSerializable and opts the class into it. ClassField rejects such a property in a
 * class without JsonSerializable, so that a decode of other classes loads no extra class.
 *
 * @internal
 */
final class NullableJsonValues
{
    /** @var array<class-string, list<string>> */
    private static array $names = [];

    /**
     * Returns the names of the properties.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty>|null $properties The properties of the class, if already read.
     * @return list<string>
     */
    public static function of(ReflectionClass $class, array|null $properties = null): array
    {
        return self::$names[$class->getName()] ??= self::declared($properties ?? $class->getProperties());
    }

    /**
     * Rejects a class without #[Field] that implements JsonSerializable but has no such property.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties The properties of the class.
     * @throws DecodeError
     */
    public static function requireOne(ReflectionClass $class, array $properties): void
    {
        $names = self::of($class, $properties);
        if ($names === []) {
            throw UnsupportedTarget::jsonSerializable($class->getName());
        }
    }

    /**
     * @param list<ReflectionProperty> $properties
     * @return list<string>
     */
    private static function declared(array $properties): array
    {
        $names = [];
        foreach ($properties as $property) {
            if (!$property->isPublic() || $property->isStatic() || !ClassField::holdsNullableJsonValue($property)) {
                continue;
            }
            $names[] = $property->getName();
        }
        return $names;
    }
}
