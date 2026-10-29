<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
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
     * accepts only null for it. The type tree reads a class named like a resource type as a resource.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function field(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): Node|null
    {
        $name = FieldTypeNameResolver::resolve($field, $type);
        $nullable = $type->allowsNull();
        if ($name === ArrayObject::class || $nullable && array_key_exists($name, self::RESOURCES)) {
            return null;
        }
        $node = self::named($name, $field->getName());
        return match (true) {
            $node instanceof DecodeError => $nullable ? null : throw $node,
            $nullable => NullableNode::of($node),
            default => $node,
        };
    }

    /**
     * Compiles a resolved type name. Returns the error for a type whose values JSON cannot represent, a resource or a
     * non-backed enum without custom serialization, because a union skips such a member if another member remains.
     *
     * @throws DecodeError If the type cannot be decoded for another reason.
     * @throws ReflectionException
     */
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
     * Compiles the name of a class or an enum, or returns the error for an enum whose values JSON cannot represent.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function named(string $name, string $path): Node|DecodeError
    {
        return match (true) {
            array_key_exists($name, self::IMPRECISE) => throw UnsupportedField::imprecise($path, $name),
            enum_exists($name) => TargetClass::unrepresentableEnum($name, $path) ?? EnumNode::of($name),
            default => self::object($name, $path),
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
        if ($value instanceof BackedEnum) {
            TargetClass::validateEnum($value::class, $path);
        }
        return LiteralNode::of($literal);
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
