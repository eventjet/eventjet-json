<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedClass;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;

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
    /**
     * The JSON names of the renamed properties of each class, and the names of its properties that declare any
     * attribute. A field whose property declares none skips looking up its own attributes, which would cost a call per
     * field on every first decode.
     *
     * @var array<class-string, array{array<string, string>, array<string, true>}>
     */
    private static array $fields = [];

    /**
     * Rejects a class whose objects JSON cannot restore, or returns the JSON names of its renamed properties, keyed by
     * property name, and the names of its properties that declare any attribute. A class decoder applies these rules to
     * every class, at the root or nested.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array{array<string, string>, array<string, true>}
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
        $error = self::unrepresentableEnum($enum, $field);
        if ($error !== null) {
            throw $error;
        }
    }

    /**
     * Returns the error for a non-backed enum without custom serialization, whose values JSON cannot represent, so that
     * a union can skip it if another member remains. Throws for an enum that JSON represents but cannot decode.
     *
     * @param string|null $field The field that declares the enum, or null at the root or for the item of a JsonType.
     * @throws DecodeError
     */
    public static function unrepresentableEnum(string $enum, string|null $field): DecodeError|null
    {
        // Checked before the BackedEnum check narrows $enum, after which Mago reports this check as unreachable.
        $serialized = is_a($enum, JsonSerializable::class, allow_string: true);
        if (!is_a($enum, BackedEnum::class, allow_string: true)) {
            $error = UnsupportedClass::nonBackedEnum($field, $enum);
            return $serialized ? throw $error : $error;
        }
        return $serialized ? throw UnsupportedClass::jsonSerializableEnum($field, $enum) : null;
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
        return self::fields($class)[0];
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array{array<string, string>, array<string, true>}
     * @throws DecodeError
     */
    private static function fields(ReflectionClass $class): array
    {
        return self::$fields[$class->getName()] ?? self::resolve($class);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array{array<string, string>, array<string, true>}
     * @throws DecodeError
     */
    private static function resolve(ReflectionClass $class): array
    {
        $className = $class->getName();
        $properties = $class->getProperties();
        [$attributed, $annotated] = PropertyAttributes::of($class, $properties);
        if ($annotated === []) {
            if ($class->implementsInterface(JsonSerializable::class)) {
                NullableJsonValues::requireDecodable($class);
            }
            return self::$fields[$className] = [[], $attributed];
        }
        return self::$fields[$className] = [
            FieldNameRules::resolve($class, $properties, $annotated),
            $attributed,
        ];
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
     * Rejects a class whose own declarations JSON cannot restore, or returns the JSON names of its renamed properties
     * and the names of its properties that declare any attribute.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array{array<string, string>, array<string, true>}
     * @throws DecodeError
     */
    private static function validateDeclarations(ReflectionClass $class): array
    {
        try {
            $fields = self::fields($class);
            $constructor = $class->getConstructor();
            if ($constructor !== null && !$constructor->isPublic()) {
                throw UnsupportedTarget::inaccessibleConstructor($constructor);
            }
            return $fields;
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
    }
}
