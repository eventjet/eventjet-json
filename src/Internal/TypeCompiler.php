<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

use function enum_exists;
use function in_array;

/**
 * Compiles native field declarations into decoder nodes, rejecting declarations that cannot round-trip.
 *
 * @internal
 */
final class TypeCompiler
{
    /** The native types of collections. */
    private const array COLLECTIONS = ['array', ArrayObject::class];

    /**
     * Compiles a field from its native declaration and, unless it is a collection, its PHPDoc type. Collection fields
     * read their PHPDoc type while compiling the native declaration. A PHPDoc number literal that PHP and PHPStan can
     * read differently makes any field unsupported.
     *
     * @throws ReflectionException
     */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        $doc = PhpDocFieldType::resolve($field);
        $number = $doc === null ? null : PhpDocFieldType::unsupportedNumber($field, $doc);
        if ($number !== null) {
            return $number;
        }
        $isCollection = $this->isCollection($field, $type);
        return $doc === null || $isCollection
            ? $this->declared($field, $type)
            : LiteralFieldCompiler::compile($this, $field, $type, $doc);
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
        return UnsupportedField::intersection($field->getName(), (string) $type);
    }

    /** @throws ReflectionException */
    public function named(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): Node|DecodeError
    {
        $name = FieldTypeNameResolver::resolve($field, $type);
        if (in_array($name, self::COLLECTIONS, strict: true)) {
            return $type->allowsNull()
                ? new CollectionUnionCompiler($this, $field)->compile($type)
                : new CollectionCompiler($field)->compile($name);
        }
        if (in_array($name, ['mixed', 'object', stdClass::class], strict: true)) {
            return UnsupportedField::imprecise($field->getName(), $name);
        }
        if ($type->isBuiltin()) {
            return new ScalarNode($name, $type->allowsNull());
        }
        if (enum_exists($name)) {
            return ClassFieldTypeValidator::enum($field->getName(), $name, $type);
        }
        $object = ClassFieldTypeValidator::object($field->getName(), $name, $type->allowsNull());
        return $object ?? new UncheckedNode($name, $field->getName());
    }

    /** Whether a field is declared as a collection, alone or in a union. */
    private function isCollection(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): bool
    {
        return $type instanceof ReflectionUnionType
            ? NativeUnionCompiler::hasCollection($type)
            : $type instanceof ReflectionNamedType
            && in_array(FieldTypeNameResolver::resolve($field, $type), self::COLLECTIONS, strict: true);
    }
}
