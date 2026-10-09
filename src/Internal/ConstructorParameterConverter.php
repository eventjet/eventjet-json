<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use function enum_exists;

/** @internal */
final class ConstructorParameterConverter
{
    /**
     * @throws ReflectionException
     */
    public static function create(
        ReflectionParameter $field,
        ReflectionType|null $type,
        string|false|null $docComment,
        ListType|MapType|TupleType|CollectionUnionType|false|null $resolved,
    ): FieldValueConverter|null {
        $collection = $resolved === false ? null : $resolved;
        $builtin = $type instanceof ReflectionNamedType && $type->isBuiltin();
        if ($collection !== null || $docComment === false) {
            if ($builtin && $collection === null) {
                return null;
            }
            return new FieldValueConverter($field, $collection);
        }
        $typeName = $type instanceof ReflectionNamedType && !$builtin
            ? FieldTypeNameResolver::resolve($field, $type)
            : null;
        if ($typeName !== null && !enum_exists($typeName)) {
            return new FieldValueConverter($field, $collection);
        }
        $hasLiteralPhpDoc = FieldTypeNameResolver::hasLiteralPhpDoc($field, $collection, $docComment);
        $literals = $hasLiteralPhpDoc ? PhpDocLiteralField::resolve($field) : null;
        if ($literals !== null) {
            return new PhpDocLiteralFieldValueConverter($field, $collection, $literals);
        }
        return $builtin ? null : new FieldValueConverter($field, $collection);
    }
}
