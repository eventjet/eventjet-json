<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_any;
use function count;
use function in_array;
use function is_string;

/** @internal */
final class PhpDocLiteralField
{
    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection = null,
    ): array|null {
        if ($collection !== null) {
            return null;
        }
        $type = PhpDocFieldType::resolve($field);
        if ($type === null) {
            return null;
        }
        $members = $type->name === '|' ? $type->arguments : [$type];
        $names = [];
        foreach ($members as $member) {
            $resolved = PhpDocItemTypeResolver::resolve($field, $member);
            if ($resolved instanceof CollectionUnionType) {
                $names = [...$names, ...$resolved->names()];
                continue;
            }
            if (!is_string($resolved)) {
                return null;
            }
            $names[] = $resolved;
        }
        $hasLiteral =
            count($names) === 1 && in_array('null', $names, strict: true)
            || array_any($names, static fn(string $name): bool => PhpDocLiteral::value($name) !== null);
        return $hasLiteral ? $names : null;
    }
}
