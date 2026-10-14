<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use function enum_exists;

/** @internal */
final readonly class ConstructorParameter
{
    public string $name;
    public ReflectionType|null $type;
    public string $typeName;
    public bool $builtin;
    private bool $variadic;
    private bool $recoverable;

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws ReflectionException
     */
    public function __construct(
        public ReflectionParameter $reflection,
        ReflectionClass $class,
    ) {
        $this->name = $reflection->getName();
        $this->type = $reflection->getType();
        $this->typeName = $this->type instanceof ReflectionNamedType
            ? FieldTypeNameResolver::resolve($reflection, $this->type)
            : (string) $this->type;
        $this->builtin = $this->type instanceof ReflectionNamedType && $this->type->isBuiltin();
        $this->variadic = $reflection->isVariadic();
        $property = $class->hasProperty($this->name) ? $class->getProperty($this->name) : null;
        $this->recoverable = $property !== null && $property->isPublic() && !$property->isStatic();
    }

    /**
     * Returns the node that converts the argument, or null if the argument needs only its native type check.
     *
     * @throws ReflectionException
     */
    public function resolveType(): Node|DecodeError|null
    {
        if ($this->variadic) {
            return UnsupportedField::variadic($this->name);
        }
        if ($this->type === null) {
            return UnsupportedField::untyped($this->name);
        }
        $node = $this->compile($this->type);
        if ($node instanceof DecodeError) {
            return $node;
        }
        return $this->recoverable ? $node : UnsupportedField::withoutProperty($this->name);
    }

    /**
     * Returns the node of the argument, or null if the native type check is the whole check: a scalar node checks
     * nothing beyond it.
     *
     * @throws ReflectionException
     */
    private function compile(ReflectionType $type): Node|DecodeError|null
    {
        $node = new TypeCompiler()->field($this->reflection, $type);
        return $node instanceof ScalarNode ? null : $node;
    }

    /**
     * Returns the native type check of the argument as type name, and the type that errors report if the argument
     * accepts null. Unions and enums have none, because their nodes check the value.
     *
     * @return array{string, string|null}|null
     */
    public function nativeCheck(): array|null
    {
        $type = $this->type;
        if (!$type instanceof ReflectionNamedType || !$this->builtin && enum_exists($this->typeName)) {
            return null;
        }
        return [$this->typeName, $type->allowsNull() ? FieldTypeNameResolver::withNull($this->typeName) : null];
    }
}
