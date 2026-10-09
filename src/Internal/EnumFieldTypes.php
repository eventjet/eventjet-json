<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use WeakMap;

use function array_map;
use function enum_exists;

/** @internal */
final class EnumFieldTypes
{
    /** @var WeakMap<ReflectionParameter|ReflectionProperty, ReflectionNamedType|array<array-key, string>|false>|null */
    private static WeakMap|null $types = null;

    /** @throws ReflectionException */
    public static function isBackedEnum(string $type): bool
    {
        return enum_exists($type) && new ReflectionEnum($type)->isBacked();
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
