<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function enum_exists;

/** @internal */
final class LiteralFieldTypes
{
    /**
     * Whether PHPDoc literals can restrict a native type: scalars, backed enums, and unions without collections.
     * Collection items declare their literals in the collection type.
     */
    public static function accepts(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return !NativeUnionCompiler::hasCollection($type);
        }
        return (
            $type instanceof ReflectionNamedType
            && ($type->isBuiltin() ? $type->getName() !== 'array' : enum_exists($type->getName()))
        );
    }
}
