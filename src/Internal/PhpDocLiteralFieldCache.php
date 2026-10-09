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
    /** @var array<string, array{string|false, bool, list<string>|null}> */
    private static array $literalPresence = [];

    /** @throws ReflectionException */
    public static function hasLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        if ($docComment === false) {
            return false;
        }

        $key = self::cacheKey($field);
        $comment =
            $docComment
            ?? (
                $field instanceof ReflectionParameter
                    ? $field->getDeclaringFunction()->getDocComment()
                    : $field->getDocComment()
            );
        if (array_key_exists($key, self::$literalPresence) && self::$literalPresence[$key][0] === $comment) {
            return self::$literalPresence[$key][1];
        }
        if (!self::mayContainLiteral($field, $comment)) {
            self::$literalPresence[$key] = [$comment, false, null];
            return false;
        }

        $resolved = PhpDocLiteralField::resolveUncached($field);
        self::$literalPresence[$key] = [$comment, true, $resolved];
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
        self::hasLiteral($field);
        $cached = self::$literalPresence[self::cacheKey($field)] ?? ['', false, null];
        return $cached[2];
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
