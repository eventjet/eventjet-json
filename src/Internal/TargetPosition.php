<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;

use function is_a;

/**
 * Where a declaration names the class or enum that JSON is decoded into, which decides the rules it must follow.
 * Errors in the field position name the field. In the other positions, the caller names the class.
 *
 * @internal
 */
enum TargetPosition
{
    /**
     * The class or enum of the root value of a document, or the class of an object that an item or a field already
     * accepted under its own, stricter rules.
     */
    case Root;

    /** The class or enum of the items of a JsonType. */
    case Item;

    /** The class or enum that a field declares, alone or inside a collection, union, or tuple. */
    case Field;

    /**
     * Rejects a class or an enum that cannot be decoded in this position, or returns the JSON names of the renamed
     * properties of a class, keyed by property name.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param string $path The field that declares the class.
     * @return array<string, string>
     * @throws DecodeError
     */
    public function validate(ReflectionClass $class, string $path = ''): array
    {
        if (!$class->isEnum()) {
            return $this->validateClass($class, $path);
        }
        $this->validateEnum($class->getName(), $path);
        return [];
    }

    /**
     * Rejects an enum that cannot be decoded: a non-backed enum, which has no JSON representation, and an enum with a
     * custom JSON representation, which may not match its backing values.
     *
     * @param string $path The field that declares the enum.
     * @throws DecodeError
     */
    public function validateEnum(string $enum, string $path = ''): void
    {
        $field = $this === self::Field;
        // Checked before the BackedEnum check narrows $enum, after which Mago reports this check as unreachable.
        $serialized = is_a($enum, JsonSerializable::class, allow_string: true);
        if (!is_a($enum, BackedEnum::class, allow_string: true)) {
            throw $field ? UnsupportedFieldClass::nonBackedEnum($path, $enum) : UnsupportedTarget::nonBackedEnum($enum);
        }
        if ($serialized) {
            throw $field
                ? UnsupportedFieldClass::jsonSerializableEnum($path, $enum)
                : UnsupportedTarget::jsonSerializable($enum);
        }
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>
     * @throws DecodeError
     */
    private function validateClass(ReflectionClass $class, string $path): array
    {
        $name = $class->getName();
        $field = $this === self::Field;
        if ($class->isInterface()) {
            throw $field ? UnsupportedFieldClass::interface($path, $name) : UnsupportedTarget::interface();
        }
        if ($class->isAbstract()) {
            throw $field ? UnsupportedFieldClass::abstractClass($path, $name) : UnsupportedTarget::abstractClass();
        }
        // Only a root object is known to be an instance of the class itself. Items and fields may hold subclasses.
        if ($this !== self::Root && !$class->isFinal()) {
            throw $field ? UnsupportedFieldClass::nonFinal($path, $name) : UnsupportedTarget::nonFinalItem();
        }
        return TargetClass::validate($class);
    }
}
