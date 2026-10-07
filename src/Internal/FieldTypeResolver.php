<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class FieldTypeResolver
{
    /** @var array<class-string, array<class-string<ReflectionParameter|ReflectionProperty>, MetadataCache<ListType|MapType|TupleType|FieldCollectionUnionType|DecodeError|false|null>>> */
    private static array $collections = [];

    /**
     * @param class-string $class
     * @param ReflectionParameter|ReflectionProperty $field A constructor parameter or public property.
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): ListType|MapType|TupleType|FieldCollectionUnionType|DecodeError|null {
        $kind = $field::class;
        $name = $field->getName();
        /** @var MetadataCache<ListType|MapType|TupleType|FieldCollectionUnionType|DecodeError|false|null> $cache */
        $cache = self::$collections[$class][$kind] ?? new MetadataCache();
        self::$collections[$class][$kind] = $cache;

        $resolved = $cache->resolve(
            $name,
            /** @throws ReflectionException */ static fn(): ListType|MapType|TupleType|FieldCollectionUnionType|DecodeError|false|null => FieldTypeValidator::validate(
                $class,
                $field,
            ),
        );

        return $resolved === false ? null : $resolved;
    }
}
