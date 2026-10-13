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
use ReflectionEnum;
use ReflectionException;
use stdClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function is_a;

/**
 * Compiles a resolved type name: a scalar, a literal, null, an enum, or a class.
 *
 * @internal
 */
final readonly class NamedTypeCompiler
{
    private const array RESOURCES = ['resource', 'open-resource', 'closed-resource'];

    /** @throws ReflectionException */
    public function compile(string $name, string $path): Node|DecodeError
    {
        return match (true) {
            in_array($name, ['bool', 'callable', 'float', 'int', 'string'], strict: true) => new ScalarNode(
                $name,
                false,
            ),
            $name === 'null' => new NullOnlyNode('null'),
            PhpDocType::literalSyntax($name) => $this->literal($name, $path),
            in_array($name, ['mixed', 'object', stdClass::class], strict: true) => UnsupportedField::imprecise(
                $path,
                $name,
            ),
            in_array($name, self::RESOURCES, strict: true) => UnsupportedField::nonEncodable($path, $name),
            enum_exists($name) => $this->enum($name, $path),
            default => $this->object($name, $path),
        };
    }

    /**
     * Returns the error of a union member whose values have no JSON representation: a resource, or a non-backed enum
     * without custom serialization. Unions skip such members if another member remains.
     *
     * @throws ReflectionException
     */
    public static function unrepresentable(PhpDocType $member, Node|DecodeError|null $node): DecodeError|null
    {
        $name = $member->name;
        $enum = $node instanceof DecodeError && enum_exists($name);
        $unrepresentable =
            in_array($name, self::RESOURCES, strict: true)
            || $enum
            && !new ReflectionEnum($name)->isBacked()
            && !is_a($name, JsonSerializable::class, allow_string: true);
        return $unrepresentable && $node instanceof DecodeError ? $node : null;
    }

    /**
     * Rejects enums whose values JSON cannot restore: non-backed enums, and backed enums with a custom JSON
     * representation, which may not match their backing values.
     *
     * @throws ReflectionException
     */
    private function enum(string $enum, string $path): Node|DecodeError
    {
        $node = EnumNode::of($enum);
        return match (true) {
            $node === null => UnsupportedFieldClass::nonBackedEnum($path, $enum),
            is_a($enum, JsonSerializable::class, allow_string: true) => $this->serializedEnum($enum, $path),
            default => $node,
        };
    }

    /** A literal enum case is subject to the rules of its enum. */
    private function literal(string $name, string $path): Node|DecodeError
    {
        $value = PhpDocLiteral::value($name);
        return $value instanceof JsonSerializable
            ? $this->serializedEnum($value::class, $path)
            : LiteralNode::of($name);
    }

    private function serializedEnum(string $enum, string $path): DecodeError
    {
        return UnsupportedFieldClass::jsonSerializableEnum($path, $enum);
    }

    /**
     * Compiles a class that JSON represents as an object. PHP checks a class that is not loaded yet when the field is
     * assigned.
     *
     * @throws ReflectionException
     */
    private function object(string $name, string $path): Node|DecodeError
    {
        $error = ClassFieldTypeValidator::validate($path, $name);
        return match (true) {
            $error !== null => $error,
            class_exists($name) => new ObjectNode($name, false),
            default => new UncheckedNode($name, $path),
        };
    }
}
