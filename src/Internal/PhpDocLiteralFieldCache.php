<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function serialize;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, list<string>|null> */
    private static array $literalPresence = [];

    /** @throws ReflectionException */
    public static function hasLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        if ($docComment === false) {
            return false;
        }

        $comment =
            $docComment
            ?? (
                $field instanceof ReflectionParameter
                    ? $field->getDeclaringFunction()->getDocComment()
                    : $field->getDocComment()
            );
        if (!self::mayContainLiteral($field, $comment)) {
            return false;
        }

        $key = self::cacheKey($field);
        if (array_key_exists($key, self::$literalPresence)) {
            return true;
        }

        $resolved = PhpDocLiteralField::resolveUncached($field);
        self::$literalPresence[$key] = $resolved;
        return true;
    }

    public static function mayContainLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        return PhpDocLiteralFieldMarker::mayContainLiteral($field, $docComment);
    }

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        if (!self::hasLiteral($field)) {
            return null;
        }

        return self::$literalPresence[self::cacheKey($field)] ?? null;
    }

    /** @throws ReflectionException */
    private static function cacheKey(ReflectionParameter|ReflectionProperty $field): string
    {
        return (
            $field instanceof ReflectionParameter
                ? serialize([
                    'parameter',
                    $field->getDeclaringClass()?->getName(),
                    $field->getDeclaringFunction()->getName(),
                    $field->getName(),
                ])
                : serialize(['property', $field->getDeclaringClass()->getName(), $field->getName()])
        );
    }
}
