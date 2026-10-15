<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

use function class_exists;
use function interface_exists;
use function is_a;

/** @internal */
final class ClassFieldTypeValidator
{
    /**
     * Rejects a referenced class that JSON cannot identify or construct. Classes that are not loaded pass. Errors in
     * the declarations of the referenced class itself name that class.
     *
     * @throws ReflectionException
     */
    public static function validate(string $field, string $type): DecodeError|null
    {
        if (interface_exists($type)) {
            return UnsupportedFieldClass::interface($field, $type);
        }

        if (!class_exists($type)) {
            return null;
        }

        $typeReflection = new ReflectionClass($type);

        if ($typeReflection->isAbstract()) {
            return UnsupportedFieldClass::abstractClass($field, $type);
        }

        if (!$typeReflection->isFinal()) {
            return UnsupportedFieldClass::nonFinal($field, $type);
        }

        if ($typeReflection->isEnum()) {
            return $typeReflection->implementsInterface(JsonSerializable::class)
                ? UnsupportedFieldClass::jsonSerializableEnum($field, $type)
                : null;
        }

        $names = TargetClass::fieldNames($typeReflection);
        return $names instanceof DecodeError ? $names->whileCreating($typeReflection->getName()) : null;
    }

    /**
     * Compiles a class that JSON represents as an object, or returns null if the class is not loaded.
     *
     * @throws ReflectionException
     */
    public static function object(string $path, string $name, bool $nullable): ObjectNode|DecodeError|null
    {
        $error = self::validate($path, $name);
        if ($error !== null) {
            return $error;
        }
        return class_exists($name) ? new ObjectNode($name, $nullable) : null;
    }

    /**
     * Compiles a field declared as an enum. A nullable field of a non-backed enum can only hold null.
     *
     * @throws ReflectionException
     */
    public static function enum(string $field, string $enum, ReflectionNamedType $type): Node|DecodeError
    {
        $nullable = $type->allowsNull();
        $node = EnumNode::of($enum);
        if ($node !== null) {
            return $nullable ? new NullableNode($node) : $node;
        }
        return $nullable && !is_a($enum, JsonSerializable::class, allow_string: true)
            ? new NullOnlyNode((string) $type)
            : UnsupportedFieldClass::nonBackedEnum($field, $enum);
    }
}
