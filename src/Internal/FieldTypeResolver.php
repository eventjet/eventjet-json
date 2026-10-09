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
use function str_contains;

/** @internal */
final class FieldTypeResolver
{
    /** @var array<class-string, array<class-string<ReflectionParameter|ReflectionProperty>, array<string, ListType|MapType|TupleType|CollectionUnionType|false>>> */
    private static array $collections = [];

    public static function literalDocComment(
        ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $type,
    ): string|false {
        if ($type instanceof ReflectionUnionType) {
            $hasCollection = FieldCollectionUnionResolver::hasCollection($type);
            if ($hasCollection) {
                return false;
            }
        }
        if ($type instanceof ReflectionNamedType) {
            $typeName = FieldTypeNameResolver::resolve($field, $type);
            $isCollection = in_array($typeName, ['array', ArrayObject::class], strict: true);
            if ($isCollection) {
                return false;
            }
        }

        return $field->getDocComment();
    }

    /**
     * @return array{string|false, bool}
     * @throws ReflectionException
     */
    public static function literalMetadata(
        ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $type,
    ): array {
        $docComment = $field->getDocComment();
        if ($docComment === false || !str_contains($docComment, '@var')) {
            return [$docComment, false];
        }

        $docComment = self::literalDocComment($field, $type);
        if ($docComment === false) {
            return [$docComment, false];
        }

        $mayContainLiteralMarker = FieldTypeNameResolver::mayContainLiteralMarker($docComment);
        if (!$mayContainLiteralMarker) {
            return [$docComment, false];
        }

        $hasLiteralPhpDoc = FieldTypeNameResolver::hasLiteralPhpDoc($field, docComment: $docComment);
        return [$docComment, $hasLiteralPhpDoc];
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
        bool|null $hasLiteralPhpDoc = null,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|null {
        if ($hasLiteralPhpDoc === false) {
            $docComment = false;
        }
        $kind = $field::class;
        $name = $field->getName();
        $resolved = self::$collections[$class][$kind][$name] ?? null;
        if ($resolved !== null) {
            return $resolved === false ? null : $resolved;
        }

        $resolved = FieldTypeValidator::validate($class, $field, $docComment, $hasLiteralPhpDoc);
        if ($resolved !== null && !$resolved instanceof DecodeError) {
            self::$collections[$class][$kind][$name] = $resolved;
        }

        return $resolved === false ? null : $resolved;
    }
}
