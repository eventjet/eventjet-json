<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function str_contains;
use function strpbrk;

/** @internal */
final class PhpDocLiteralMarkerFilter
{
    public static function mayContain(string $docComment): bool
    {
        $simpleLiteralMarker = strpbrk($docComment, characters: "'\"0123456789");
        $hasClassConstant = str_contains($docComment, '::');
        $hasTrue = str_contains($docComment, 'true');
        $hasFalse = str_contains($docComment, 'false');
        $hasNull = str_contains($docComment, 'null');
        $hasParam = str_contains($docComment, '@param');
        $hasVar = str_contains($docComment, '@var');

        return (
            $simpleLiteralMarker !== false
            || $hasClassConstant
            || $hasTrue
            || $hasFalse
            || $hasNull
            && ($hasParam || $hasVar)
        );
    }
}
