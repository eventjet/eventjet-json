<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use JsonSerializable;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use WeakMap;

use function array_map;
use function enum_exists;
use function in_array;
use function is_a;

/** @internal */
final class EnumFieldTypes
{
    /** @var WeakMap<ReflectionParameter|ReflectionProperty, ReflectionNamedType|array<array-key, string>|false>|null */
    private static WeakMap|null $types = null;

    public static function isNonEncodable(string $type): bool
    {
        if (in_array($type, ['resource', 'open-resource', 'closed-resource'], strict: true)) {
            return true;
        }

        return (
            enum_exists($type)
            && !new ReflectionEnum($type)->isBacked()
            && !is_a($type, JsonSerializable::class, allow_string: true)
        );
    }

    public static function hasEnum(ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType && enum_exists($member->getName())) {
                return true;
            }
        }

        return false;
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
