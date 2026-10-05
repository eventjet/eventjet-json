<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function ltrim;
use function preg_match;
use function str_starts_with;

/** @internal */
final class FieldTypeNameResolver
{
    private const string REFINED_INTEGER_PATTERN = '(?:int\\s*<\\s*(?:min|-?[0-9]+)\\s*,\\s*(?:max|-?[0-9]+)\\s*>|(?:non-)?(?:positive|negative)-int)';

    public const string COLLECTION_TYPE_PATTERN =
        self::REFINED_INTEGER_PATTERN . '|non-empty-string|numeric-string|\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*';

    public static function resolve(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): string
    {
        $name = $type->getName();
        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($name === 'self') {
            return $declaringClass->getName();
        }

        if ($name !== 'parent') {
            return $name;
        }

        $parent = $declaringClass->getParentClass();

        return $parent === false ? $name : $parent->getName();
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        if ($type === 'non-empty-string' || $type === 'numeric-string') {
            return 'string';
        }

        $isRefinedInteger = preg_match('/\\A' . self::REFINED_INTEGER_PATTERN . '\\z/', $type) === 1;

        if ($isRefinedInteger) {
            return 'int';
        }

        if (in_array($type, ['bool', 'float', 'int', 'string'], strict: true)) {
            return $type;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($type === 'self') {
            return $declaringClass->getName();
        }

        $resolvedType = str_starts_with($type, '\\')
            ? ltrim($type, characters: '\\')
            : $declaringClass->getNamespaceName() . '\\' . $type;

        return enum_exists($resolvedType) || class_exists($resolvedType) || interface_exists($resolvedType)
            ? $resolvedType
            : null;
    }
}
