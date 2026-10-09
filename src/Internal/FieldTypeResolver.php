<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function in_array;

/** @internal */
final class FieldTypeResolver
{
    /** @var array<class-string, array<class-string<ReflectionParameter|ReflectionProperty>, array<string, ListType|MapType|TupleType|CollectionUnionType|false>>> */
    private static array $collections = [];

    public static function literalDocComment(
        ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $type,
    ): string|false|null {
        if ($type instanceof ReflectionUnionType && FieldCollectionUnionResolver::hasCollection($type)) {
            return null;
        }
        if (
            $type instanceof ReflectionNamedType
            && in_array(FieldTypeNameResolver::resolve($field, $type), ['array', ArrayObject::class], strict: true)
        ) {
            return null;
        }

        return $field->getDocComment();
    }

    /**
     * @param class-string $class
     * @param ReflectionParameter|ReflectionProperty $field A constructor parameter or public property.
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|null {
        $kind = $field::class;
        $name = $field->getName();
        $resolved = self::$collections[$class][$kind][$name] ?? null;
        if ($resolved !== null) {
            return $resolved === false ? null : $resolved;
        }

        $resolved = FieldTypeValidator::validate($class, $field, $docComment);
        if ($resolved !== null && !$resolved instanceof DecodeError) {
            self::$collections[$class][$kind][$name] = $resolved;
        }

        return $resolved === false ? null : $resolved;
    }
}
