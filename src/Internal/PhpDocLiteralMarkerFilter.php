<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function preg_match;
use function str_contains;
use function strpbrk;

/** @internal */
final class PhpDocLiteralMarkerFilter
{
    public static function mayContain(string $docComment): bool
    {
        if (strpbrk($docComment, characters: "'\"0123456789") !== false) {
            return true;
        }
        if (
            str_contains($docComment, '::')
            || str_contains($docComment, 'true')
            || str_contains($docComment, 'false')
        ) {
            return true;
        }
        return (
            (str_contains($docComment, '@param') || str_contains($docComment, '@var'))
            && str_contains($docComment, 'null')
            && preg_match('~@(param|var)[ \\t]+[^\\r\\n*]+?\\bnull\\b~', subject: $docComment) === 1
        );
    }
}
