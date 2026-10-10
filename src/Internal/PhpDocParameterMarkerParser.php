<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionFunctionAbstract;

use function preg_match_all;
use function trim;

use const PREG_SET_ORDER;

/** @internal */
final class PhpDocParameterMarkerParser
{
    /** @return array<string, bool> */
    public static function parse(ReflectionFunctionAbstract $function, string|false|null $docComment): array
    {
        $doc = $docComment ?? $function->getDocComment();
        if ($doc === false) {
            return [];
        }
        $mayContainMarker = PhpDocLiteralFieldMarker::mayContainLiteralMarker($doc);
        if (!$mayContainMarker) {
            return [];
        }

        $matches = [];
        preg_match_all(
            '~@param[ \t]+(?P<type>[^\r\n*]+?)[ \t]+(?:&|\.\.\.)?\$(?P<name>[A-Za-z_][A-Za-z0-9_]*)(?:[ \t]|\r?\n|$)~',
            $doc,
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
