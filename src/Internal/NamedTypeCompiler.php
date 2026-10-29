<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
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
        'float' => true,
        'int' => true,
        'string' => true,
    ];

    private const array IMPRECISE = [
        'callable' => true,
        'iterable' => true,
        'mixed' => true,
        'object' => true,
        stdClass::class => true,
    ];

    private const array RESOURCES = ['resource' => true, 'open-resource' => true, 'closed-resource' => true];

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function compile(PhpDocType $type, string $path): Node
    {
        $name = $type->name;
        return match (true) {
            $type->literal !== null => self::literal($type->literal, $path),
            array_key_exists($name, self::SCALARS) => new ScalarNode($name, false),
            $name === 'null' => new NullOnlyNode('null'),
            array_key_exists($name, self::RESOURCES) => throw UnsupportedField::nonEncodable($path, $name),
            default => self::named($name, $path),
        };
    }

    /**
     * Compiles the name of a class or an enum.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function named(string $name, string $path): Node
    {
        return match (true) {
            array_key_exists($name, self::IMPRECISE) => throw UnsupportedField::imprecise($path, $name),
            enum_exists($name) => self::enum($name, $path),
            default => self::object($name, $path),
        };
    }

    /**
     * Whether the values of a union member that failed to compile have no JSON representation: a resource, or a
     * non-backed enum without custom serialization. Unions skip such members if another member remains.
     *
     * @throws ReflectionException
     */
    public static function unrepresentable(string $name): bool
    {
        return (
            array_key_exists($name, self::RESOURCES)
            || enum_exists($name)
            && !new ReflectionEnum($name)->isBacked()
            && !is_a($name, JsonSerializable::class, allow_string: true)
        );
    }

    /**
     * Rejects enums whose values JSON cannot restore: non-backed enums, and backed enums with a custom JSON
     * representation, which may not match their backing values.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function enum(string $enum, string $path): Node
    {
        $node = EnumNode::of($enum);
        return match (true) {
            $node === null => throw UnsupportedFieldClass::nonBackedEnum($path, $enum),
            is_a($enum, JsonSerializable::class, allow_string: true) => throw self::serializedEnum($enum, $path),
            default => $node,
        };
    }

    /**
     * A literal enum case is subject to the rules of its enum.
     *
     * @throws DecodeError
     */
    private static function literal(PhpDocLiteral $literal, string $path): Node
    {
        $value = $literal->value;
        return $value instanceof JsonSerializable
            ? throw self::serializedEnum($value::class, $path)
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
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function object(string $name, string $path): Node
    {
        if (interface_exists($name)) {
            throw UnsupportedFieldClass::interface($path, $name);
        }
        if (!class_exists($name)) {
            return new UncheckedNode($name, $path, nullable: false);
        }
        $class = new ReflectionClass($name);
        if ($class->isAbstract()) {
            throw UnsupportedFieldClass::abstractClass($path, $name);
        }
        if (!$class->isFinal()) {
            throw UnsupportedFieldClass::nonFinal($path, $name);
        }
        try {
            TargetClass::fieldNames($class);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        return new ObjectNode($name, false);
    }
}
