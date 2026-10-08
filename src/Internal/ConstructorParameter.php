<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/** @internal */
final readonly class ConstructorParameter
{
    public string $name;
    public ReflectionType|null $type;
    public string $typeName;
    public bool $builtin;
    public bool $variadic;
    public bool $recoverable;

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
}
