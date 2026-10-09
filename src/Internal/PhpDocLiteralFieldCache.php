<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function enum_exists;
use function preg_match;
use function preg_match_all;
use function serialize;
use function trim;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, array{bool, list<string>|null}> */
    private static array $literalPresence = [];
    /** @var array<string, array<string, bool>> */
    private static array $parameterMarkers = [];

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
        $hasLiteral = self::mayContainLiteral($field, $docComment);
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
        if ($field instanceof ReflectionParameter) {
            $function = $field->getDeclaringFunction();
            $key = serialize([
                $function->getDeclaringClass()?->getName(),
                $function->getName(),
                $function->getFileName(),
                $function->getStartLine(),
            ]);
            self::$parameterMarkers[$key] ??= self::parameterMarkers($function, $docComment);
            return self::$parameterMarkers[$key][$field->getName()] ?? false;
        }
        $doc = $docComment ?? $field->getDocComment();
        $matches = [];
        preg_match('~@var[ \t]+(?P<type>[^\r\n*]+)~', (string) $doc, $matches);
        /** @var array{0: non-falsy-string, 1: non-empty-string, type: non-empty-string} $captures */
        $captures = $matches;
        $type = $captures['type'] ?? null;
        return $type !== null && self::isLiteralMarker(trim($type));
    }

    /** @return array<string, bool> */
    private static function parameterMarkers(ReflectionFunctionAbstract $function, string|false|null $docComment): array
    {
        $doc = $docComment ?? $function->getDocComment();
        $matches = [];
        preg_match_all(
            '~@param[ \t]+(?P<type>[^\r\n*]+?)[ \t]+(?:&|\.\.\.)?\$(?P<name>[A-Za-z_][A-Za-z0-9_]*)(?:[ \t]|\r?\n|$)~',
            (string) $doc,
            $matches,
            PREG_SET_ORDER,
        );
        /** @var list<array{type: non-empty-string, name: non-empty-string}> $matches */
        $parameters = [];
        foreach ($matches as $match) {
            $parameters[$match['name']] = self::isLiteralMarker(trim($match['type']));
        }
        return $parameters;
    }

    private static function isLiteralMarker(string $type): bool
    {
        return preg_match('~[\'"\d:]|\b(?:true|false|null|[A-Z][A-Za-z0-9_]*)\b~', subject: $type) === 1;
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
