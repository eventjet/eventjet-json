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
    /** @var class-string */
    public string $class;
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
        private string|false|null $docComment = null,
    ) {
        $this->class = $class->getName();
        $this->name = $reflection->getName();
        $this->type = $reflection->getType();
        $this->typeName = ConstructorValueValidator::typeName($reflection, $this->type);
        $this->builtin = $this->type instanceof ReflectionNamedType && $this->type->isBuiltin();
        $this->variadic = $reflection->isVariadic();
        $property = RootTypeValidator::declarations($class)[$this->name] ?? null;
        $this->recoverable = $property !== null && $property->isPublic() && !$property->isStatic();
    }

    /** @throws ReflectionException */
    public function converter(ListType|MapType|TupleType|CollectionUnionType|false|null $resolved): FieldValueConverter|null
    {
        $collection = $resolved === false ? null : $resolved;
        if ($collection !== null || $this->docComment === false) {
            return $this->builtin && $collection === null
                ? null
                : new FieldValueConverter($this->reflection, $collection);
        }
        return ConstructorParameterConverter::create($this->reflection, $this->type, $this->docComment, $resolved);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public function resolveType(string $class): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null
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
        $hasLiteralPhpDoc = $this->docComment === false ? false : null;
        $resolved = $this->type instanceof ReflectionNamedType
            ? FieldTypeValidator::validateNamedType(
                $class,
                $this->reflection,
                $this->type,
                $this->docComment,
                $hasLiteralPhpDoc,
            )
            : FieldTypeValidator::validate($class, $this->reflection, $this->docComment, $hasLiteralPhpDoc);
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
