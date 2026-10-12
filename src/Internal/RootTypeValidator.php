<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionProperty;

use function array_push;
use function enum_exists;

/** @internal */
final class RootTypeValidator
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function fieldNames(ReflectionClass $class): array|DecodeError
    {
        return self::$names[$class->getName()] ?? self::discoverFieldNames($class);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     */
    private static function discoverFieldNames(ReflectionClass $class): array|DecodeError
    {
        $className = $class->getName();
        $properties = self::mappedFields($class);
        if ($properties === []) {
            return $class->implementsInterface(JsonSerializable::class)
                ? UnsupportedTarget::jsonSerializable()
                : (self::$names[$className] = []);
        }
        $names = FieldNames::resolve($class, $properties);
        return $names instanceof DecodeError ? $names : (self::$names[$className] = $names);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function validate(ReflectionClass $class): DecodeError|null
    {
        $className = $class->getName();

        if ($class->isInterface()) {
            return UnsupportedTarget::interface();
        }

        if ($class->isAbstract()) {
            return UnsupportedTarget::abstractClass();
        }

        $names = self::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }

        $constructor = $class->getConstructor();

        if ($constructor !== null && !$constructor->isPublic()) {
            $visibility = $constructor->isPrivate() ? 'private' : 'protected';

            return UnsupportedTarget::inaccessibleConstructor($visibility);
        }

        $classIsNonBackedEnum = self::isNonBackedEnum($className);

        if ($classIsNonBackedEnum) {
            return UnsupportedTarget::nonBackedEnum($className);
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return list<ReflectionProperty>
     */
    private static function mappedFields(ReflectionClass $class): array
    {
        $properties = $class->getProperties();
        // Reflection omits private ancestor properties from the effective child declarations.
        $parent = $class->getParentClass();
        while ($parent !== false) {
            array_push($properties, ...$parent->getProperties(ReflectionProperty::IS_PRIVATE));
            $parent = $parent->getParentClass();
        }
        $mapped = [];
        foreach ($properties as $property) {
            if ($property->getAttributes(Field::class) === []) {
                continue;
            }
            $mapped[] = $property;
        }
        return $mapped;
    }
}
