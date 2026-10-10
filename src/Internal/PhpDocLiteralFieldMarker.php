<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function enum_exists;
use function preg_match;
use function str_contains;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocLiteralFieldMarker
{
    public static function mayContainLiteral(
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
    ): bool {
        if ($field instanceof ReflectionParameter) {
            $hasMarker = PhpDocParameterMarkerCache::hasMarker($field, $docComment);
            if (!$hasMarker) {
                return false;
            }
        }
        $native = $field->getType();
        if ($native instanceof ReflectionNamedType && !$native->isBuiltin()) {
            $isEnum = enum_exists($native->getName());
            if (!$isEnum) {
                return false;
            }
        }
        if ($field instanceof ReflectionParameter) {
            return true;
        }
        $doc = $docComment ?? $field->getDocComment();
        if ($doc === false || $doc === '') {
            return false;
        }
        if (!str_contains($doc, '@var')) {
            return false;
        }
        $matches = [];
        preg_match('~@var[ \t]+(?P<type>[^\r\n*]+)~', $doc, $matches);
        return self::isLiteralMarker(trim($matches['type'] ?? ''));
    }

    public static function isLiteralMarker(string $type): bool
    {
        if (strpbrk($type, characters: "'\"0123456789") !== false) {
            return true;
        }

        return preg_match('~::|\b(?:true|false)\b|^\s*null\s*$~', subject: $type) === 1;
    }

    public static function mayContainLiteralMarker(string $docComment): bool
    {
        return PhpDocLiteralMarkerFilter::mayContain($docComment);
    }

    /** @return array<string, bool> */
    public static function parameterMarkers(ReflectionFunctionAbstract $function, string|false|null $docComment): array
    {
        return PhpDocParameterMarkerParser::parse($function, $docComment);
    }
}
