<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;

/** @internal */
final class TupleTypeResolver
{
    /**
     * @param list<PhpDocTupleEntry> $types
     * @return TupleType|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field, array $types): TupleType|null
    {
        $resolved = [];
        $required = 0;
        $hasOptional = false;

        foreach ($types as $type) {
            if ($type->key !== null && $type->key !== (string) count($resolved)) {
                return null;
            }

            if ($hasOptional && !$type->optional) {
                return null;
            }

            $hasOptional = $type->optional;
            $required += $type->optional ? 0 : 1;
            $item = PhpDocItemTypeResolver::resolve($field, $type->type);

            $hasCollections = $item instanceof CollectionUnionType && $item->hasCollections();
            if ($item === null || $item instanceof NestedCollectionType || $hasCollections) {
                return null;
            }

            $resolved[] = $item;
        }

        return new TupleType($resolved, $required);
    }
}
