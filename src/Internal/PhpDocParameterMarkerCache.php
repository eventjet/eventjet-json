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
    /** @var array<string, true> */
    private static array $functionsWithoutDoc = [];

    public static function hasMarker(ReflectionParameter $field, string|false|null $docComment = null): bool
    {
        $function = $field->getDeclaringFunction();
        $functionName = $function->getName();
        $functionKey = ($field->getDeclaringClass()?->getName() ?? '') . '::' . $functionName;
        if ($functionName === '{closure}') {
            $functionKey .= ':' . $function->getFileName() . ':' . $function->getStartLine();
        }
        if ($docComment === null && array_key_exists($functionKey, self::$functionsWithoutDoc)) {
            return false;
        }
        $doc = $docComment ?? $function->getDocComment();
        if ($doc === false || $doc === '') {
            if ($docComment === null) {
                self::$functionsWithoutDoc[$functionKey] = true;
            }
            return false;
        }
        if (!array_key_exists($doc, self::$parameterMarkers)) {
            self::$parameterMarkers[$doc] = self::parameterMarkers($function, $doc);
        }
        return self::$parameterMarkers[$doc][$field->getName()] ?? false;
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
