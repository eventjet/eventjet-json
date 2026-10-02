<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;

/** @internal */
final class ScalarListTypeResolver
{
    /** @return 'bool'|'float'|'int'|'string'|null */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): string|null
    {
        $docComment = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $fieldPattern = $field instanceof ReflectionParameter
            ? '\\s+\\$' . $field->getName() . '(?:\\s|$)'
            : '(?:\\s|$)';
        $matches = [];
        $matched = preg_match(
            '/@'
            . ($field instanceof ReflectionParameter ? 'param' : 'var')
            . '\\s+list\\s*<\\s*(?<type>bool|float|int|string)\\s*>'
            . $fieldPattern
            . '/',
            $docComment,
            $matches,
        );

        return match ($matched === 1 ? $matches['type'] ?? null : null) {
            'bool' => 'bool',
            'float' => 'float',
            'int' => 'int',
            'string' => 'string',
            default => null,
        };
    }
}
