<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\DecodeFailure;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionProperty;

use function array_filter;
use function array_push;
use function enum_exists;

/**
 * Checks that a class can be a decode target, and resolves the JSON names that #[Field] gives to its properties, once
 * per class.
 *
 * @internal
 */
final class TargetClass
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * Rejects a class that cannot be instantiated from a JSON object, or returns the JSON names of its renamed
     * properties, keyed by property name.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeFailure
     */
    public static function validate(ReflectionClass $class): array
    {
        $className = $class->getName();

        if ($class->isInterface()) {
            throw UnsupportedTarget::interface();
        }

        if ($class->isAbstract()) {
            throw UnsupportedTarget::abstractClass();
        }

        $names = self::fieldNames($class);

        $constructor = $class->getConstructor();

        if ($constructor !== null && !$constructor->isPublic()) {
            $visibility = $constructor->isPrivate() ? 'private' : 'protected';

            throw UnsupportedTarget::inaccessibleConstructor($visibility);
        }

        $classIsNonBackedEnum = self::isNonBackedEnum($className);

        if ($classIsNonBackedEnum) {
            throw UnsupportedTarget::nonBackedEnum($className);
        }

        return $names;
    }

    /**
     * Returns the JSON names of the renamed properties, keyed by property name. All other properties use their own
     * names in JSON.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeFailure
     * @phpstan-impure
     */
    public static function fieldNames(ReflectionClass $class): array
    {
        return self::$names[$class->getName()] ?? self::resolve($class);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeFailure
     */
    private static function resolve(ReflectionClass $class): array
    {
        $className = $class->getName();
        $properties = $class->getProperties();
        $annotated = array_filter(
            [...$properties, ...self::privateAncestorProperties($class)],
            static fn(ReflectionProperty $property): bool => $property->getAttributes(Field::class) !== [],
        );
        if ($annotated === []) {
            return $class->implementsInterface(JsonSerializable::class)
                ? throw UnsupportedTarget::jsonSerializable($class)
                : (self::$names[$className] = []);
        }
        return self::$names[$className] = FieldNameRules::resolve($class, $properties, $annotated);
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

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
