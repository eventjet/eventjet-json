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
    /** @var array<class-string, array<class-string<ReflectionParameter|ReflectionProperty>, array<string, ListType|MapType|TupleType|CollectionUnionType|false>>> */
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
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|null {
        $kind = $field::class;
        $name = $field->getName();
        $resolved = self::$collections[$class][$kind][$name] ?? null;
        if ($resolved !== null) {
            return $resolved === false ? null : $resolved;
        }

        $resolved = FieldTypeValidator::validate($class, $field);
        if ($resolved !== null && !$resolved instanceof DecodeError) {
            self::$collections[$class][$kind][$name] = $resolved;
        }

        return $resolved === false ? null : $resolved;
    }
}
