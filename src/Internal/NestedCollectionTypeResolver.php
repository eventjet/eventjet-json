<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function strcasecmp;

/** @internal */
final class NestedCollectionTypeResolver
{
    /** @throws ReflectionException */
    public static function resolve(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): NestedCollectionType|null {
        $nullable = false;
        if ($type->name === '|') {
            $arguments = $type->argumentPair();
            if ($arguments === null) {
                return null;
            }
            [$first, $second] = $arguments;
            $firstIsNull = $first->isPlainName('null');
            $secondIsNull = $second->isPlainName('null');
            $type = match (true) {
                $firstIsNull => $second,
                $secondIsNull => $first,
                default => null,
            };
            if ($type === null) {
                return null;
            }
            $nullable = true;
        }

        $arguments = $type->arguments;
        if (in_array($type->name, ['list', 'non-empty-list'], strict: true) && count($arguments) === 1) {
            $item = PhpDocItemTypeResolver::resolve($field, $arguments[0]);
            return $item === null
                ? null
                : new NestedCollectionType(new ListType($item, $type->name === 'non-empty-list'), $nullable);
        }

        $pair = $type->argumentPair();
        if ($pair === null) {
            return null;
        }
        [$keyType, $valueType] = $pair;
        $hasStringKey = $keyType->isPlainName('string');
        if (!$hasStringKey) {
            return null;
        }
        $container = FieldTypeNameResolver::resolvePhpDoc($field, $type->name);
        $arrayObject = strcasecmp($container ?? $type->name, ArrayObject::class) === 0;
        if (!$arrayObject && !in_array($type->name, ['non-empty-array', 'non-empty-map'], strict: true)) {
            return null;
        }
        $item = PhpDocItemTypeResolver::resolve($field, $valueType);
        return $item === null ? null : new NestedCollectionType(new MapType($item, $arrayObject), $nullable);
    }
}
