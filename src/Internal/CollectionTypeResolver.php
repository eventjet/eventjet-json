<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function sprintf;

/** @internal */
final class CollectionTypeResolver
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $nativeType,
    ): ListType|MapType|TupleType|DecodeError {
        if (!$field->getType() instanceof ReflectionNamedType) {
            return self::invalidDeclaration($class, $field);
        }

        $type = PhpDocFieldType::resolve($field);
        $collection = $nativeType === ArrayObject::class
            ? MapTypeResolver::resolve($class, $field, $type, $nativeType)
            : self::array($class, $field, $type);

        if ($collection === null) {
            return self::invalidDeclaration($class, $field);
        }
        if ($collection instanceof DecodeError) {
            return $collection;
        }

        $error = CollectionTypeValidator::validate($class, $field->getName(), $collection);

        return $error ?? $collection;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function array(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType|null $type,
    ): ListType|MapType|TupleType|DecodeError|null {
        if ($type === null) {
            return null;
        }
        if ($type->name === 'array{}') {
            return TupleTypeResolver::resolve($field, $type->entries);
        }
        if ($type->name === 'list' || $type->name === 'non-empty-list') {
            $item = count($type->arguments) === 1 ? PhpDocItemTypeResolver::resolve($field, $type->arguments[0]) : null;
            return $item === null ? null : new ListType($item, $type->name === 'non-empty-list');
        }
        return MapTypeResolver::resolve($class, $field, $type, 'array');
    }

    /** @param class-string $class */
    private static function invalidDeclaration(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): DecodeError {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s has a missing or unrecognized collection declaration. Use @%s with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        ));
    }
}
