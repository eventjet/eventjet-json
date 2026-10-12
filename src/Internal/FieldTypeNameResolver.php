<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function enum_exists;
use function interface_exists;
use function strcasecmp;
use function strpbrk;
use function strtr;

/** @internal */
final class FieldTypeNameResolver
{
    private const array PRIMITIVE_PHPDOC = [
        'non-empty-string' => 'string',
        'numeric-string' => 'string',
        'literal-string' => 'string',
        'positive-int' => 'int',
        'negative-int' => 'int',
        'non-positive-int' => 'int',
        'non-negative-int' => 'int',
        'non-zero-int' => 'int',
        'bool' => 'bool',
        'float' => 'float',
        'int' => 'int',
        'string' => 'string',
    ];

    public static function resolve(ReflectionParameter|ReflectionProperty $field, ReflectionNamedType $type): string
    {
        $name = $type->getName();
        if ($name === 'self') {
            /** @var ReflectionClass<object> $declaringClass */
            $declaringClass = $field->getDeclaringClass();
            return $declaringClass->getName();
        }

        if ($name !== 'parent') {
            return strcasecmp($name, ArrayObject::class) === 0 ? ArrayObject::class : $name;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();
        $parent = $declaringClass->getParentClass();

        return $parent === false ? $name : $parent->getName();
    }

    /** @return array<string, class-string<ConstructorParameter>> */
    public static function parameterClasses(ReflectionMethod|null $constructor): array
    {
        $docComment = (string) $constructor?->getDocComment();
        $mayDeclareLiteral = self::mayDeclareLiteral($docComment);
        return $mayDeclareLiteral ? PhpDocLiteralTags::parameters($docComment) : [];
    }

    public static function mayDeclareLiteral(string $docComment): bool
    {
        $markers = strtr($docComment, ['true' => '"', 'false' => '"', ' null' => '"', "\tnull" => '"']);
        return strpbrk($markers, characters: "'\"0123456789:") !== false;
    }

    public static function expected(ReflectionNamedType $type, string $name): string
    {
        return $type->allowsNull() && $name !== 'null' ? $name . '|null' : $name;
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolvePhpDoc(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        $primitive = self::PRIMITIVE_PHPDOC[$type] ?? null;
        if ($primitive !== null) {
            return $primitive;
        }

        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();

        if ($type === 'self') {
            return $declaringClass->getName();
        }

        $resolvedType = PhpDocClassNameResolver::resolve($declaringClass, $type);

        return enum_exists($resolvedType) || class_exists($resolvedType) || interface_exists($resolvedType)
            ? $resolvedType
            : null;
    }
}
