<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

use function array_filter;
use function array_push;

/**
 * Checks the declarations of a class that JSON objects are decoded into, and resolves the JSON names that #[Field]
 * gives to its properties, once per class.
 *
 * @internal
 */
final class TargetClass
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * Rejects a class whose own declarations JSON cannot restore, or returns the JSON names of its renamed properties,
     * keyed by property name. Errors name the class. {@see TargetPosition::validate()} applies the rules of the
     * position first.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeError
     */
    public static function validate(ReflectionClass $class, TargetPosition $position): array
    {
        try {
            $names = self::fieldNames($class);
            // A field leaves the constructor to the decoder of its class, which checks it when the first object of the
            // class is decoded.
            if ($position !== TargetPosition::Field) {
                self::validateConstructor($class);
            }
            return $names;
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
    }

    /**
     * Returns the JSON names of the renamed properties, keyed by property name. All other properties use their own
     * names in JSON.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeError
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
     * @throws DecodeError
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
                ? throw UnsupportedTarget::jsonSerializable($className)
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

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws DecodeError
     */
    private static function validateConstructor(ReflectionClass $class): void
    {
        $constructor = $class->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            throw UnsupportedTarget::inaccessibleConstructor($constructor->isPrivate() ? 'private' : 'protected');
        }
    }
}
