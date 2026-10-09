<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function is_bool;
use function str_contains;

/** @internal */
final class ScalarPhpDocPresence
{
    /** @var array<string, bool> */
    private static array $presence = [];

    public static function has(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection = null,
    ): bool
    {
        if ($collection !== null) {
            return false;
        }

        if ($field instanceof ReflectionParameter) {
            $function = $field->getDeclaringFunction();
            $declaringClass = $field->getDeclaringClass();
            $key = ($declaringClass?->getName() ?? '') . '::' . $function->getName()
                . '@' . $function->getFileName() . ':' . $function->getStartLine() . '::$' . $field->getName();
            $doc = $function->getDocComment();
            $needle = '$' . $field->getName();
        } else {
            $key = $field->getDeclaringClass()->getName() . '::$' . $field->getName();
            $doc = $field->getDocComment();
            $needle = '/**';
        }

        $cached = self::$presence[$key] ?? null;
        if (is_bool($cached)) {
            return $cached;
        }

        $hasComment = str_contains((string) $doc, $needle);
        self::$presence[$key] = $hasComment;

        return $hasComment;
    }
}
