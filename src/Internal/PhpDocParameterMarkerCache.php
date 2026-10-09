<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionFunctionAbstract;
use ReflectionParameter;

use function array_key_exists;
use function preg_match_all;
use function trim;

/** @internal */
final class PhpDocParameterMarkerCache
{
    /** @var array<string, array<string, bool>> */
    private static array $parameterMarkers = [];
    /** @var array<string, string> */
    private static array $functionDocComments = [];

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
            && ($docComment === null || $docComment === self::$functionDocComments[$functionKey])
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
        self::$parameterMarkers[$functionKey] = self::parameterMarkers($function, $doc);
        self::$functionDocComments[$functionKey] = $doc;
        return self::$parameterMarkers[$functionKey][$field->getName()] ?? false;
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
        /** @var list<array{0: string, type: non-empty-string, 1: non-empty-string, name: non-falsy-string, 2: non-falsy-string}> $matches */
        $parameters = [];
        foreach ($matches as $match) {
            $parameters[$match['name']] = PhpDocLiteralFieldMarker::isLiteralMarker(trim($match['type']));
        }
        return $parameters;
    }
}
