<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function enum_exists;
use function preg_match;
use function serialize;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, bool> */
    private static array $literalMarkers = [];
    /** @var array<string, array{bool, list<string>|null}> */
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
        if (array_key_exists($key, self::$literalPresence)) {
            $cached = self::$literalPresence[$key];
            return $cached[0];
        }
        $hasLiteral =
            self::mayContainLiteral($field, $docComment)
            && PhpDocFieldType::resolve($field)?->containsLiteral() === true;
        $resolved = $hasLiteral ? PhpDocLiteralField::resolveUncached($field) : null;
        self::$literalPresence[$key] = [$hasLiteral, $resolved];
        return $hasLiteral;
    }

    public static function mayContainLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        $native = $field->getType();
        if ($native instanceof ReflectionNamedType && !$native->isBuiltin() && !enum_exists($native->getName())) {
            return false;
        }
        $doc =
            $docComment
            ?? (
                $field instanceof ReflectionParameter
                    ? $field->getDeclaringFunction()->getDocComment()
                    : $field->getDocComment()
            );
        if ($doc === false) {
            return false;
        }
        $matches = [];
        $found = $field instanceof ReflectionParameter
            ? preg_match(
                '~@param[ \t]+(?P<type>[^\r\n*]+?)[ \t]+(?:&|\.\.\.)?\$'
                . preg_quote($field->getName(), delimiter: '~')
                . '(?:[ \t]|\r?\n|$)~',
                $doc,
                $matches,
            )
            : preg_match('~@var[ \t]+(?P<type>[^\r\n*]+)~', $doc, $matches);
        if ($found !== 1) {
            return false;
        }
        /** @var array{0: non-falsy-string, 1: non-empty-string, type: non-empty-string} $captures */
        $captures = $matches;
        $type = trim($captures['type']);
        return self::$literalMarkers[$type] ??=
            preg_match('~[\'"\d:]|\b(?:true|false|null|[A-Z][A-Za-z0-9_]*)\b~', subject: $type) === 1;
    }

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        self::hasLiteral($field);
        $cached = self::$literalPresence[self::cacheKey($field)] ?? [false, null];
        return $cached[1];
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
