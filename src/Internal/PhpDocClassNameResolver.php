<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function explode;
use function interface_exists;
use function ltrim;
use function str_starts_with;
use function strtolower;
use function substr;

/** @internal */
final class PhpDocClassNameResolver
{
    /** @return class-string|null */
    public static function resolvePhpDocClass(ReflectionParameter|ReflectionProperty $field, string $type): string|null
    {
        /** @var ReflectionClass<object> $declaringClass */
        $declaringClass = $field->getDeclaringClass();
        $resolvedType = self::resolve($declaringClass, $type);
        return class_exists($resolvedType) || interface_exists($resolvedType) ? $resolvedType : null;
    }

    /** @param ReflectionClass<object> $class */
    public static function resolve(ReflectionClass $class, string $type): string
    {
        if ($type === 'self') {
            return $class->getName();
        }

        $namespace = $class->getNamespaceName();
        if (str_starts_with($type, '\\')) {
            return ltrim($type, characters: '\\');
        }
        if (str_starts_with(strtolower($type), 'namespace\\')) {
            return ltrim($namespace . '\\' . substr($type, offset: 10), characters: '\\');
        }

        $parts = explode('\\', $type, limit: 2);
        $imports = PhpDocImports::forClass($class);
        $import = $imports[strtolower($parts[0])] ?? null;
        $suffix = $parts[1] ?? null;

        return $import === null
            ? ltrim($namespace . '\\' . $type, characters: '\\')
            : $import . ($suffix === null ? '' : '\\' . $suffix);
    }
}
