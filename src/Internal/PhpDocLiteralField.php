<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_any;
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
        $hasLiteral = array_any(
            $names,
            static fn(string $name): bool => $name === 'null' || PhpDocLiteral::value($name) !== null,
        );
        return $hasLiteral ? $names : null;
    }
}
