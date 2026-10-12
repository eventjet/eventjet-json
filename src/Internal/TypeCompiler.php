<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
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

/**
 * Compiles native field declarations of one class into decoder nodes, rejecting declarations that cannot round-trip.
 *
 * @internal
 */
final class TypeCompiler
{
    /**
     * Declared classes that are not loaded. Decoders that depend on them must be compiled again later.
     *
     * @var list<string>
     */
    public private(set) array $unresolved = [];

    /** @param class-string $class */
    public function __construct(
        public readonly string $class,
    ) {}

    /** @throws ReflectionException */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
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
        if ($name === 'array' || $name === ArrayObject::class) {
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
            return EnumCompiler::field($this->class, $field->getName(), $name, $type);
        }
        $object = $this->object($field->getName(), $name, $type->allowsNull());
        if ($object !== null) {
            return $object;
        }
        $this->unresolved[] = $name;
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
}
