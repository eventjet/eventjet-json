<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function enum_exists;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function strpbrk;
use function trim;

use const PREG_SET_ORDER;

/** @internal */
final class PhpDocLiteralFieldMarker
{
    public static function mayContainLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        $native = $field->getType();
        if ($native instanceof ReflectionNamedType && !$native->isBuiltin() && !enum_exists($native->getName())) {
            return false;
        }
        if ($field instanceof ReflectionParameter) {
            return PhpDocParameterMarkerCache::hasMarker($field, $docComment);
        }
        $doc = $docComment ?? $field->getDocComment();
        if ($doc === false || $doc === '') {
            return false;
        }
        if (!str_contains($doc, '@var') || !self::isLiteralMarker($doc)) {
            return false;
        }
        $matches = [];
        preg_match('~@var[ \t]+(?P<type>[^\r\n*]+)~', $doc, $matches);
        return self::isLiteralMarker(trim($matches['type'] ?? ''));
    }

    public static function isLiteralMarker(string $type): bool
    {
        if (strpbrk($type, characters: "'\"0123456789:") !== false) {
            return true;
        }

        return preg_match('~\b(?:true|false|null)\b~', subject: $type) === 1;
    }

    /** @return array<string, bool> */
    public static function parameterMarkers(ReflectionFunctionAbstract $function, string|false|null $docComment): array
    {
        $doc = $docComment ?? $function->getDocComment();
        if (!self::isLiteralMarker((string) $doc)) {
            return [];
        }
        $matches = [];
        preg_match_all(
            '~@param[ \t]+(?P<type>[^\r\n*]+?)[ \t]+(?:&|\.\.\.)?\$(?P<name>[A-Za-z_][A-Za-z0-9_]*)(?:[ \t]|\r?\n|$)~',
            (string) $doc,
            $matches,
            PREG_SET_ORDER,
        );
        /** @var list<array{0: string, type: non-empty-string, 1: non-empty-string, name: non-falsy-string, 2: non-falsy-string}> $matches */
        $parameters = [];
        foreach ($matches as $match) {
            $parameters[$match['name']] = self::isLiteralMarker(trim($match['type']));
        }
        return $parameters;
    }
}
