<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_a;
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

    public static function mayDeclareLiteral(string $docComment): bool
    {
        $markers = strtr($docComment, ['true' => '"', 'false' => '"', ' null' => '"', "\tnull" => '"']);
        return strpbrk($markers, characters: "'\"0123456789:") !== false;
    }

    /**
     * Scalar and backed enum declarations need no further type validation, which keeps the validators unloaded.
     */
    public static function isPlainType(ReflectionType|null $type): bool
    {
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }
        $name = $type->getName();
        return (
            $type->isBuiltin()
                ? !in_array($name, ['array', 'mixed', 'object'], strict: true)
                : enum_exists($name) && is_a($name, BackedEnum::class, allow_string: true)
        );
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
