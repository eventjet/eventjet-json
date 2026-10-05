<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function strcasecmp;

/** @internal */
final class ArrayObjectTypeResolver
{
    public static function matches(ReflectionParameter|ReflectionProperty $field): bool
    {
        $matches = [];
        $matched = preg_match(
            CollectionTypeResolver::declarationPattern(
                $field,
                '(?<container>' . FieldTypeNameResolver::CLASS_NAME_PATTERN . ')\\s*<\\s*string\\s*,.+>',
            ),
            CollectionTypeResolver::docComment($field),
            $matches,
        );
        if ($matched !== 1) {
            return false;
        }
        /** @var array{0: string, container: non-empty-string, 1: non-empty-string} $matches */
        $container = $matches['container'];
        $resolved = FieldTypeNameResolver::resolvePhpDoc($field, $container);

        return strcasecmp($resolved ?? $container, ArrayObject::class) === 0;
    }
}
