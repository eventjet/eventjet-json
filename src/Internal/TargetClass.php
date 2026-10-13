<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionProperty;

use function array_filter;
use function array_push;
use function enum_exists;
use function sprintf;

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
     * Rejects a class that cannot be instantiated from a JSON object.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function validate(ReflectionClass $class): DecodeError|null
    {
        $className = $class->getName();

        if ($class->isInterface()) {
            return DecodeError::nonInstantiableTarget(
                $className,
                'Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            );
        }

        if ($class->isAbstract()) {
            return DecodeError::nonInstantiableTarget(
                $className,
                'Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            );
        }

        $names = self::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }

        $constructor = $class->getConstructor();

        if ($constructor !== null && !$constructor->isPublic()) {
            $visibility = $constructor->isPrivate() ? 'private' : 'protected';

            return DecodeError::nonInstantiableTarget($className, sprintf(
                'Target class has a %s constructor, which cannot be called to create the object.',
                $visibility,
            ));
        }

        $classIsNonBackedEnum = self::isNonBackedEnum($className);

        if ($classIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($className, $className);
        }

        return null;
    }

    /**
     * Returns the JSON names of the renamed properties, keyed by property name. All other properties use their own
     * names in JSON.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function fieldNames(ReflectionClass $class): array|DecodeError
    {
        return self::$names[$class->getName()] ?? self::resolve($class);
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

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
