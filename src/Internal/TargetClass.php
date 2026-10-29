<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\UnsupportedClass;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

use function array_filter;
use function array_push;
use function is_a;

/**
 * Decides whether a class or an enum can be decoded where a declaration names it, and resolves the JSON names that
 * #[Field] gives to the properties of a class, once per class. Every decode loads this class, so the rules live here
 * rather than in a class of their own.
 *
 * A field or a JsonType item may hold an instance of a subclass, whose runtime class JSON does not identify, so its
 * class must be final. Only the root object of a document is known to be an instance of exactly its class. Errors in a
 * field name the field. Errors in the declarations of the class itself name the class.
 *
 * @internal
 */
final class TargetClass
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * Rejects a class whose objects JSON cannot restore, or returns the JSON names of its renamed properties, keyed by
     * property name. A class decoder applies these rules to every class, at the root or nested.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeError
     */
    public static function validate(ReflectionClass $class): array
    {
        self::validateConcrete($class, null);
        return self::validateDeclarations($class);
    }

    /**
     * Rejects a class or an enum that a field or a JsonType item cannot hold.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param string|null $field The field that declares the class, or null for the item of a JsonType.
     * @throws DecodeError
     */
    public static function validateNested(ReflectionClass $class, string|null $field = null): void
    {
        if (!$class->isEnum()) {
            self::validateConcrete($class, $field);
            if (!$class->isFinal()) {
                throw UnsupportedClass::nonFinal($field, $class->getName());
            }
            self::validateDeclarations($class);
            return;
        }
        self::validateEnum($class->getName(), $field);
    }

    /**
     * Rejects an enum that cannot be decoded: a non-backed enum, which has no JSON representation, and an enum with a
     * custom JSON representation, which may not match its backing values.
     *
     * @param string|null $field The field that declares the enum, or null at the root or for the item of a JsonType.
     * @throws DecodeError
     */
    public static function validateEnum(string $enum, string|null $field = null): void
    {
        // Checked before the BackedEnum check narrows $enum, after which Mago reports this check as unreachable.
        $serialized = is_a($enum, JsonSerializable::class, allow_string: true);
        if (!is_a($enum, BackedEnum::class, allow_string: true)) {
            throw UnsupportedClass::nonBackedEnum($field, $enum);
        }
        if ($serialized) {
            throw UnsupportedClass::jsonSerializableEnum($field, $enum);
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
    private static function validateConcrete(ReflectionClass $class, string|null $field): void
    {
        if ($class->isInterface()) {
            throw UnsupportedClass::interface($field, $class->getName());
        }
        if ($class->isAbstract()) {
            throw UnsupportedClass::abstractClass($field, $class->getName());
        }
    }

    /**
     * Rejects a class whose own declarations JSON cannot restore, or returns the JSON names of its renamed properties.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeError
     */
    private static function validateDeclarations(ReflectionClass $class): array
    {
        try {
            $names = self::fieldNames($class);
            $constructor = $class->getConstructor();
            if ($constructor !== null && !$constructor->isPublic()) {
                throw UnsupportedTarget::inaccessibleConstructor($constructor);
            }
            return $names;
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
    }
}
