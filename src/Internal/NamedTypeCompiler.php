<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_key_exists;
use function class_exists;
use function enum_exists;
use function interface_exists;
use function is_a;

/**
 * Compiles a resolved type name: a scalar, a literal, null, an enum, or a class.
 *
 * @internal
 */
final class NamedTypeCompiler
{
    private const array SCALARS = [
        'bool' => true,
        'callable' => true,
        'float' => true,
        'int' => true,
        'string' => true,
    ];

    private const array IMPRECISE = ['mixed' => true, 'object' => true, stdClass::class => true];

    private const array RESOURCES = ['resource' => true, 'open-resource' => true, 'closed-resource' => true];

    /**
     * Compiles a field whose native type names a class or an enum. Returns null for builtin types, and for a nullable
     * type whose class or enum fails, because the union of the type tree reports it.
     *
     * @throws ReflectionException
     */
    public static function field(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType $type,
    ): Node|DecodeError|null {
        if ($type->isBuiltin()) {
            return null;
        }
        $name = FieldTypeNameResolver::resolve($field, $type);
        $node = $name === ArrayObject::class ? null : self::named($name, $field->getName());
        return $type->allowsNull() ? NullableNode::field($node) : $node;
    }

    /** @throws ReflectionException */
    public static function compile(PhpDocType $type, string $path): Node|DecodeError
    {
        $name = $type->name;
        return match (true) {
            $type->literal !== null => self::literal($type->literal, $path),
            array_key_exists($name, self::SCALARS) => new ScalarNode($name, false),
            $name === 'null' => new NullOnlyNode('null'),
            array_key_exists($name, self::RESOURCES) => UnsupportedField::nonEncodable($path, $name),
            default => self::named($name, $path),
        };
    }

    /**
     * Compiles the name of a class or an enum.
     *
     * @throws ReflectionException
     */
    private static function named(string $name, string $path): Node|DecodeError
    {
        return match (true) {
            array_key_exists($name, self::IMPRECISE) => UnsupportedField::imprecise($path, $name),
            enum_exists($name) => self::enum($name, $path),
            default => self::object($name, $path),
        };
    }

    /**
     * Returns the error of a union member that failed to compile if its values have no JSON representation: a resource,
     * or a non-backed enum without custom serialization. Unions skip such members if another member remains.
     *
     * @throws ReflectionException
     */
    public static function unrepresentable(string $name, DecodeError|null $error): DecodeError|null
    {
        $enum = $error !== null && enum_exists($name);
        $unrepresentable =
            array_key_exists($name, self::RESOURCES)
            || $enum
            && !new ReflectionEnum($name)->isBacked()
            && !is_a($name, JsonSerializable::class, allow_string: true);
        return $unrepresentable ? $error : null;
    }

    /**
     * Rejects enums whose values JSON cannot restore: non-backed enums, and backed enums with a custom JSON
     * representation, which may not match their backing values.
     *
     * @throws ReflectionException
     */
    private static function enum(string $enum, string $path): Node|DecodeError
    {
        $node = EnumNode::of($enum);
        return match (true) {
            $node === null => UnsupportedFieldClass::nonBackedEnum($path, $enum),
            is_a($enum, JsonSerializable::class, allow_string: true) => self::serializedEnum($enum, $path),
            default => $node,
        };
    }

    /** A literal enum case is subject to the rules of its enum. */
    private static function literal(PhpDocLiteral $literal, string $path): Node|DecodeError
    {
        $value = $literal->value;
        return $value instanceof JsonSerializable
            ? self::serializedEnum($value::class, $path)
            : LiteralNode::of($literal);
    }

    private static function serializedEnum(string $enum, string $path): DecodeError
    {
        return UnsupportedFieldClass::jsonSerializableEnum($path, $enum);
    }

    /**
     * Compiles a class that JSON represents as an object, rejecting a class that JSON cannot identify or construct.
     * PHP checks a class that is not loaded yet when the field is assigned. Errors in the declarations of the class
     * itself name that class.
     *
     * @throws ReflectionException
     */
    private static function object(string $name, string $path): Node|DecodeError
    {
        if (interface_exists($name)) {
            return UnsupportedFieldClass::interface($path, $name);
        }
        if (!class_exists($name)) {
            return new UncheckedNode($name, $path);
        }
        $class = new ReflectionClass($name);
        if ($class->isAbstract()) {
            return UnsupportedFieldClass::abstractClass($path, $name);
        }
        if (!$class->isFinal()) {
            return UnsupportedFieldClass::nonFinal($path, $name);
        }
        $names = TargetClass::fieldNames($class);
        return $names instanceof DecodeError ? $names->whileCreating($class->getName()) : new ObjectNode($name, false);
    }
}
