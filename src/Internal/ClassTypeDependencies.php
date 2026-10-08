<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function enum_exists;
use function in_array;
use function preg_match;

/** @internal */
final class ClassTypeDependencies
{
    /** @return iterable<string> */
    public static function field(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|false|null $resolved,
    ): iterable {
        foreach (self::names($field, $resolved) as $name) {
            $isNonEncodable = FieldTypeValidator::isNonEncodable($name);
            $literal = preg_match('/\A[\x27"0-9.+-]|::/', $name) === 1;
            if (
                in_array($name, ['bool', 'float', 'int', 'string', 'true', 'false', 'null'], strict: true)
                || $literal
                || $isNonEncodable
                || enum_exists($name)
            ) {
                continue;
            }
            yield $name;
        }
    }

    /** @return iterable<string> */
    private static function names(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|false|null $resolved,
    ): iterable {
        if ($resolved !== null && $resolved !== false) {
            yield from self::collection($resolved);
            return;
        }
        $type = $field->getType();
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($members as $member) {
            yield from $member instanceof ReflectionNamedType && !$member->isBuiltin()
                ? [FieldTypeNameResolver::resolve($field, $member)]
                : [];
        }
    }

    /** @return iterable<string> */
    private static function collection(ListType|MapType|TupleType|CollectionUnionType $type): iterable
    {
        $members = match (true) {
            $type instanceof ListType => [$type->itemType],
            $type instanceof MapType => [$type->valueType],
            $type instanceof TupleType => $type->types,
            default => $type->members,
        };
        foreach ($members as $member) {
            yield from match (true) {
                $member instanceof NestedCollectionType => self::collection($member->collection),
                $member instanceof CollectionUnionType => self::collection($member),
                default => [$member],
            };
        }
    }
}
