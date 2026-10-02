<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function ltrim;
use function preg_match;
use function str_starts_with;

/** @internal */
final class ListItemTypeResolver
{
    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): string|null
    {
        return self::resolveDeclaration($field);
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolveDeclaration(ReflectionParameter|ReflectionProperty $field): string|null
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
            . '\\s+list\\s*<\\s*(?<type>\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\\s*>'
            . $fieldPattern
            . '/',
            $docComment,
            $matches,
        );
        $type = $matched === 1 ? $matches['type'] ?? null : null;

        if (!is_string($type)) {
            return null;
        }

        if (in_array($type, ['bool', 'float', 'int', 'string'], strict: true)) {
            return $type;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();
        $resolvedType = str_starts_with($type, '\\')
            ? ltrim($type, characters: '\\')
            : $declaringClass->getNamespaceName() . '\\' . $type;

        return enum_exists($resolvedType) || class_exists($resolvedType) || interface_exists($resolvedType)
            ? $resolvedType
            : null;
    }
}
