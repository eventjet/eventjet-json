<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use WeakMap;

use function array_map;
use function strcasecmp;

/** @internal */
final class EnumFieldTypes
{
    /** @var WeakMap<ReflectionParameter|ReflectionProperty, ReflectionNamedType|array<array-key, string>|false>|null */
    private static WeakMap|null $types = null;

    /** @param class-string $class */
    public static function constructorDocCommentForGraph(
        string $class,
        ReflectionMethod|null $constructor,
        ReflectionType|null $type,
    ): string|false {
        if ($type instanceof ReflectionUnionType) {
            $docComment = PhpDocParameterMarkerCache::constructorDocComment($class, $constructor);
            if ($docComment === false) {
                return false;
            }
            $hasCollection = FieldCollectionUnionResolver::hasCollection($type);
            return $hasCollection ? $docComment : FieldTypeNameResolver::literalMarkerDocComment($docComment);
        }

        $isCollection =
            $type instanceof ReflectionNamedType
            && ($type->getName() === 'array' || strcasecmp($type->getName(), \ArrayObject::class) === 0);
        return $isCollection ? PhpDocParameterMarkerCache::constructorDocComment($class, $constructor) : false;
    }

    /** @return ReflectionNamedType|array<array-key, string>|false */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): ReflectionNamedType|array|false
    {
        if (self::$types === null) {
            /** @var WeakMap<ReflectionParameter|ReflectionProperty, ReflectionNamedType|array<array-key, string>|false> $types */
            $types = new WeakMap();
            self::$types = $types;
        }
        $cached = self::$types[$field] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $type = $field->getType();
        $resolved = match (true) {
            $type instanceof ReflectionNamedType => $type,
            $type instanceof ReflectionUnionType => array_map(
                static fn(ReflectionType $member): string => (string) $member,
                $type->getTypes(),
            ),
            default => false,
        };
        self::$types[$field] = $resolved;
        return $resolved;
    }
}
