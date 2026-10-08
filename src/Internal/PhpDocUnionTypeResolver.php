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
            $literal = PhpDocLiteral::value($type->name);
            $constants =
                $type->arguments === [] && $literal === null
                    ? PhpDocConstantResolver::resolve($field, $type->name)
                    : null;
            if ($constants !== null) {
                foreach ($constants as $constant) {
                    $members[$constant] = $constant;
                }
                continue;
            }
            $resolved = self::member($field, $type);
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
    ): string|NestedCollectionType|null {
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
        $nested = NestedCollectionTypeResolver::resolve($field, $type);
        if ($nested !== null) {
            return $nested;
        }
        return PhpDocItemTypeResolver::named($field, $type);
    }
}
