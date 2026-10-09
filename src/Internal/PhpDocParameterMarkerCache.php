<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;

use function array_key_exists;

/** @internal */
final class PhpDocParameterMarkerCache
{
    /** @var array<string, array<string, bool>> */
    private static array $parameterMarkers = [];
    /** @var array<string, string> */
    private static array $functionDocComments = [];

    public static function literalComment(string|false $docComment): string|false
    {
        if ($docComment === false) {
            return false;
        }
        $hasLiteralMarker = PhpDocLiteralFieldMarker::mayContainLiteralMarker($docComment);
        return $hasLiteralMarker ? $docComment : false;
    }

    public static function hasMarker(ReflectionParameter $field, string|false|null $docComment = null): bool
    {
        $function = $field->getDeclaringFunction();
        $functionName = $function->getName();
        $functionKey = ($field->getDeclaringClass()?->getName() ?? '') . '::' . $functionName;
        if ($functionName === '{closure}') {
            $functionKey .= ':' . (string) $function->getFileName() . ':' . (string) $function->getStartLine();
        }
        if (
            $docComment !== false
            && array_key_exists($functionKey, self::$parameterMarkers)
            && ($docComment === null || $docComment === (self::$functionDocComments[$functionKey] ?? null))
        ) {
            return self::$parameterMarkers[$functionKey][$field->getName()] ?? false;
        }
        $doc = $docComment ?? $function->getDocComment();
        if ($doc === false || $doc === '') {
            if ($docComment === null) {
                self::$parameterMarkers[$functionKey] = [];
                self::$functionDocComments[$functionKey] = '';
            }
            return false;
        }
        self::$parameterMarkers[$functionKey] = PhpDocLiteralFieldMarker::parameterMarkers($function, $doc);
        self::$functionDocComments[$functionKey] = $doc;
        return self::$parameterMarkers[$functionKey][$field->getName()] ?? false;
    }
}
