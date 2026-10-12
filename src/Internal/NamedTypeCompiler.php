<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
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

    /** @param class-string $class */
    public function __construct(
        private string $class,
    ) {}

    /** @throws ReflectionException */
    public function compile(string $name, string $path): Node|DecodeError
    {
        return match (true) {
            in_array($name, ['bool', 'callable', 'float', 'int', 'string'], strict: true) => new ScalarNode(
                $this->class,
                $name,
                false,
            ),
            $name === 'null' => new NullOnlyNode($this->class, 'null'),
            PhpDocType::literalSyntax($name) => $this->literal($name, $path),
            in_array($name, ['mixed', 'object', stdClass::class], strict: true) => DecodeError::nonInstantiableField(
                $this->class,
                $path,
                'unsupported type',
                $name,
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            ),
            in_array($name, self::RESOURCES, strict: true) => DecodeError::nonInstantiableField(
                $this->class,
                $path,
                'non-JSON-encodable type',
                $name,
                ', which has no JSON representation.',
            ),
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
        $node = EnumNode::of($this->class, $enum);
        return match (true) {
            $node === null => DecodeError::nonBackedEnum($this->class, $enum, $path),
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
            : LiteralNode::of($this->class, $name);
    }

    private function serializedEnum(string $enum, string $path): DecodeError
    {
        return DecodeError::nonInstantiableField(
            $this->class,
            $path,
            'backed enum',
            $enum,
            ', which implements JsonSerializable. Its custom JSON representation may not match its backing values, so it cannot satisfy the round-trip contract.',
        );
    }

    /**
     * Compiles a class that JSON represents as an object. PHP checks a class that is not loaded yet when the field is
     * assigned.
     *
     * @throws ReflectionException
     */
    private function object(string $name, string $path): Node|DecodeError
    {
        $error = ClassFieldTypeValidator::validate($this->class, $path, $name);
        return match (true) {
            $error !== null => $error,
            class_exists($name) => new ObjectNode($this->class, $name, false),
            default => new UncheckedNode($name),
        };
    }
}
