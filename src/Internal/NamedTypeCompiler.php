<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
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
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_key_exists;
use function assert;
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

    /** Names of PHP's builtin types, which no class can have. */
    private const array BUILTINS = [
        'array' => true,
        'bool' => true,
        'callable' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'iterable' => true,
        'mixed' => true,
        'never' => true,
        'null' => true,
        'object' => true,
        'static' => true,
        'string' => true,
        'true' => true,
        'void' => true,
    ];

    /**
     * Compiles a field whose native type names a class or an enum, without the type tree and union that NodeCompiler
     * would build for it. NodeCompiler would compile the same nodes, but nullable class and enum fields are common, and
     * building the type tree and union for each one slows down the first decode in a request. Returns null for
     * `ArrayObject`, and for a nullable type whose values JSON cannot represent, because the union of the type tree
     * accepts only null for it.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function field(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): Node|null
    {
        $name = FieldTypeNameResolver::resolve($field, $type);
        $nullable = $type->allowsNull();
        $needsTree = $name === ArrayObject::class || $nullable && self::unrepresentable($name);
        if ($needsTree) {
            return null;
        }
        $node = self::named($name, $field->getName());
        return $nullable ? NullableNode::of($node) : $node;
    }

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
     */
    public static function unrepresentable(string $name): bool
    {
        return (
            array_key_exists($name, self::RESOURCES)
            || enum_exists($name)
            && !is_a($name, BackedEnum::class, allow_string: true)
            && !is_a($name, JsonSerializable::class, allow_string: true)
        );
    }

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function enum(string $enum, string $path): Node
    {
        TargetClass::validateEnum($enum, $path);
        return EnumNode::of($enum);
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
     * PHP checks a class that is not loaded yet when the field is assigned.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function object(string $name, string $path): Node
    {
        if (!class_exists($name) && !interface_exists($name)) {
            assert(!array_key_exists($name, self::BUILTINS), description: 'Builtin types compile before classes.');
            return new UncheckedNode($name, $path, nullable: false);
        }
        TargetClass::validateNested(new ReflectionClass($name), $path);
        return new ObjectNode($name, false);
    }
}
