<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use function sprintf;

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
     * @param class-string $class
     * @throws ReflectionException
     */
    public function resolveType(string $class): ListType|MapType|TupleType|FieldCollectionUnionType|DecodeError|false|null
    {
        if ($this->variadic) {
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Constructor parameter %s is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
                $this->name,
            ));
        }
        if ($this->type === null) {
            return DecodeError::nonInstantiableField(
                $class,
                $this->name,
                'no type',
                'declaration',
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            );
        }
        $resolved = FieldTypeValidator::validate($class, $this->reflection);
        if ($resolved instanceof DecodeError) {
            return $resolved;
        }
        if (!$this->recoverable) {
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Constructor parameter %s has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
                $this->name,
            ));
        }
        return $resolved;
    }
}
