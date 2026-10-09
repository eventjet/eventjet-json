<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

use function enum_exists;

/** @internal */
final class ConstructorParameterConverter
{
    /** @throws ReflectionException */
    public static function hasLiteralMarker(
        ReflectionParameter $field,
        ReflectionType|null $type,
        string|false|null $docComment,
    ): bool {
        if ($docComment === false) {
            return false;
        }
        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin() && $type->getName() === 'array') {
                return false;
            }
            if (!$type->isBuiltin() && !enum_exists($type->getName())) {
                return false;
            }
        }
        if ($type instanceof ReflectionUnionType) {
            $hasCollection = FieldCollectionUnionResolver::hasCollection($type);
            if ($hasCollection) {
                return false;
            }
        }
        return FieldTypeNameResolver::hasLiteralPhpDoc($field, docComment: $docComment);
    }

    /**
     * @throws ReflectionException
     */
    public static function create(
        ReflectionParameter $field,
        ReflectionType|null $type,
        string|false|null $_docComment,
        ListType|MapType|TupleType|CollectionUnionType|false|null $resolved,
    ): FieldValueConverter|null {
        $collection = $resolved === false ? null : $resolved;
        $builtin = $type instanceof ReflectionNamedType && $type->isBuiltin();
        if ($collection !== null) {
            return new FieldValueConverter($field, $collection);
        }
        return $builtin ? null : new FieldValueConverter($field, $collection);
    }

    /** @throws ReflectionException */
    public static function createLiteral(ReflectionParameter $field): FieldValueConverter|null
    {
        $literals = PhpDocLiteralField::resolve($field);
        if ($literals !== null) {
            return new PhpDocLiteralFieldValueConverter($field, null, $literals);
        }
        return null;
    }
}
