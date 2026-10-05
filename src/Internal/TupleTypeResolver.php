<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function count;
use function preg_match_all;

use const PREG_SET_ORDER;

/** @internal */
final class TupleTypeResolver
{
    /** @return array{types: list<'bool'|'float'|'int'|'string'|class-string>, required: int}|null */
    public static function resolve(ReflectionParameter|ReflectionProperty $field, string $types): array|null
    {
        $matches = [];
        preg_match_all(
            '/(?:([0-9]+)(\\?)?\\s*:\\s*)?(' . FieldTypeNameResolver::COLLECTION_TYPE_PATTERN . ')/',
            $types,
            $matches,
            PREG_SET_ORDER,
        );
        $resolved = [];
        $required = 0;
        $hasOptional = false;

        /** @var array{string, ''|numeric-string, ''|'?', non-falsy-string} $match */
        foreach ($matches as $match) {
            [, $index, $optionalMarker, $type] = $match;
            if ($index !== '' && $index !== (string) count($resolved)) {
                return null;
            }

            $optional = $optionalMarker === '?';

            if ($hasOptional && !$optional) {
                return null;
            }

            $hasOptional = $optional;
            $required += $optional ? 0 : 1;
            $item = FieldTypeNameResolver::resolvePhpDoc($field, $type);

            if ($item === null) {
                return null;
            }

            $resolved[] = $item;
        }

        return ['types' => $resolved, 'required' => $required];
    }
}
