<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function enum_exists;
use function preg_match;
use function str_contains;
use function trim;

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
        if (!str_contains($doc, '@var')) {
            return false;
        }
        $matches = [];
        preg_match('~@var[ \t]+(?P<type>[^\r\n*]+)~', $doc, $matches);
        return self::isLiteralMarker(trim($matches['type'] ?? ''));
    }

    public static function isLiteralMarker(string $type): bool
    {
        return preg_match('~[\'"\d:]|\b(?:true|false|null)\b~', subject: $type) === 1;
    }
}
