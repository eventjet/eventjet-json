<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function preg_match;
use function strpbrk;

/** @internal */
final class PhpDocLiteralMarkerFilter
{
    public static function mayContain(string $docComment): bool
    {
        if (strpbrk($docComment, characters: "'\"0123456789") !== false) {
            return true;
        }
        return (
            preg_match(
                '~::|\\b(?:true|false)\\b|@(param|var)[ \\t]+null(?:[ \\t]|\\r?\\n|\\*|$)~',
                subject: $docComment,
            ) === 1
        );
    }
}
