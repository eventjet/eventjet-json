<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_values;
use function class_exists;
use function in_array;
use function interface_exists;

/** @internal */
final class PhpDocUnionTypeResolver
{
    /**
     * @param list<PhpDocType> $types
     * @throws ReflectionException
     */
    public static function resolve(
        ReflectionParameter|ReflectionProperty $field,
        array $types,
    ): CollectionUnionType|null {
        $members = [];
        foreach ($types as $type) {
            $resolved = self::member($field, $type);
            if ($resolved instanceof CollectionUnionType) {
                foreach ($resolved->names() as $constant) {
                    $members[$constant] = $constant;
                }
                continue;
            }
            if ($resolved === null) {
                return null;
            }
            $name = (string) $resolved;
            $name = class_exists($name) || interface_exists($name) ? new ReflectionClass($name)->getName() : $name;
            $members[$name] = $resolved instanceof NestedCollectionType ? $resolved : $name;
        }
        return new CollectionUnionType(array_values($members));
    }

    /** @throws ReflectionException */
    private static function member(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): string|CollectionUnionType|NestedCollectionType|null {
        $isLiteral =
            $type->arguments === []
            && in_array(
                $type->name,
                ['null', 'true', 'false', 'resource', 'open-resource', 'closed-resource'],
                strict: true,
            );
        if ($isLiteral) {
            return $type->name;
        }
        return PhpDocItemTypeResolver::resolve($field, $type);
    }
}
