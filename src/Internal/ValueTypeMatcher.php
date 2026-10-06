<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionUnionType;
use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final class ValueTypeMatcher
{
    public static function matchesBuiltinUnion(mixed $value, ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType || !$member->isBuiltin()) {
                continue;
            }
            $matches = self::matches($value, $member);
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    public static function matches(mixed $value, ReflectionNamedType $type): bool
    {
        if ($value === null) {
            return $type->allowsNull();
        }

        return match ($type->getName()) {
            'array' => is_array($value) || $value instanceof stdClass,
            'bool' => is_bool($value),
            'false' => $value === false,
            'float' => is_float($value) || is_int($value),
            'int' => is_int($value),
            'null' => false,
            'string' => is_string($value),
            'true' => $value === true,
            default => true,
        };
    }
}
