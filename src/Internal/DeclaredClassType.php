<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionType;

use function class_exists;
use function enum_exists;

/** @internal */
final class DeclaredClassType
{
    /**
     * Builtin names would reach autoloaders, so only declared class types are checked.
     *
     * @return array{enum-string|null, class-string|null}
     */
    public static function resolve(ReflectionType|null $type, string $name): array
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [null, null];
        }
        return [enum_exists($name) ? $name : null, class_exists($name) ? $name : null];
    }
}
