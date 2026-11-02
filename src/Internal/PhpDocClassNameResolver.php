<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;

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
    /**
     * Returns the declared spelling of a loaded class name, which can differ in case from a PHPDoc reference.
     *
     * @throws ReflectionException
     */
    public static function canonical(string $name): string
    {
        return class_exists($name) || interface_exists($name) ? new ReflectionClass($name)->getName() : $name;
    }

    /** @param ReflectionClass<object> $class */
    public static function resolve(ReflectionClass $class, string $type): string
    {
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
