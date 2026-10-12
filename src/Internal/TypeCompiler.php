<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use JsonSerializable;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function is_a;

/**
 * Compiles native field declarations of one class into decoder nodes, rejecting declarations that cannot round-trip.
 *
 * @internal
 */
final class TypeCompiler
{
    /** @param class-string $class */
    public function __construct(
        public readonly string $class,
    ) {}

    /** @throws ReflectionException */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        $mayDeclareLiteral = FieldTypeNameResolver::mayDeclareLiteral(FieldTypeNameResolver::docComment($field));
        return $mayDeclareLiteral
            ? LiteralFieldCompiler::compile($this, $field, $type)
            : $this->declared($field, $type);
    }

    /**
     * Compiles the native declaration of a field.
     *
     * @throws ReflectionException
     */
    public function declared(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->named($field, $type);
        }
        if ($type instanceof ReflectionUnionType) {
            return NativeUnionCompiler::compile($this, $field, $type);
        }
        return DecodeError::unsupportedIntersection($this->class, $field->getName(), (string) $type);
    }

    /** @throws ReflectionException */
    public function named(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): Node|DecodeError
    {
        $name = FieldTypeNameResolver::resolve($field, $type);
        if (in_array($name, ['array', ArrayObject::class], strict: true)) {
            return $type->allowsNull()
                ? new CollectionUnionCompiler($this, $field)->compile($type)
                : new CollectionCompiler($this, $field)->compile($name);
        }
        if (in_array($name, ['mixed', 'object', stdClass::class], strict: true)) {
            return DecodeError::nonInstantiableField(
                $this->class,
                $field->getName(),
                'unsupported type',
                $name,
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            );
        }
        if ($type->isBuiltin()) {
            return new ScalarNode($this->class, $name, $type->allowsNull());
        }
        if (enum_exists($name)) {
            return $this->enum($field->getName(), $name, $type);
        }
        $object = $this->object($field->getName(), $name, $type->allowsNull());
        if ($object !== null) {
            return $object;
        }
        return new UncheckedNode($name);
    }

    /**
     * Compiles a class that JSON represents as an object, or returns null if the class is not loaded.
     *
     * @throws ReflectionException
     */
    public function object(string $path, string $name, bool $nullable): ObjectNode|DecodeError|null
    {
        $error = ClassFieldTypeValidator::validate($this->class, $path, $name);
        if ($error !== null) {
            return $error;
        }
        return class_exists($name) ? new ObjectNode($this->class, $name, $nullable) : null;
    }

    /**
     * Compiles a field declared as an enum. A nullable field of a non-backed enum can only hold null.
     *
     * @throws ReflectionException
     */
    private function enum(string $field, string $enum, ReflectionNamedType $type): Node|DecodeError
    {
        $nullable = $type->allowsNull();
        $node = EnumNode::of($this->class, $enum);
        if ($node !== null) {
            return $nullable ? new NullableNode($node) : $node;
        }
        return $nullable && !is_a($enum, JsonSerializable::class, allow_string: true)
            ? new NullOnlyNode($this->class, (string) $type)
            : DecodeError::nonBackedEnum($this->class, $enum, $field);
    }
}
