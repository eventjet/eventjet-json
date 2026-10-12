<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionProperty;

use function str_contains;
use function strpbrk;
use function strtr;

/** @internal */
final class PhpDocLiteralMarker
{
    /** @return array<string, bool> */
    public static function parameters(string $docComment): array
    {
        $mayDeclare = self::mayDeclare($docComment, '@param');
        return $mayDeclare ? PhpDocLiteralTags::parameters($docComment) : [];
    }

    public static function property(ReflectionProperty $property, string $docComment): bool
    {
        $mayDeclare = self::mayDeclare($docComment, '@var');
        return $mayDeclare && PhpDocLiteralTags::property($property, $docComment);
    }

    private static function mayDeclare(string $docComment, string $tag): bool
    {
        return (
            str_contains($docComment, $tag)
            && (
                strpbrk($docComment, characters: "'\"0123456789") !== false
                || str_contains($docComment, '::')
                || str_contains($docComment, 'true')
                || str_contains($docComment, 'false')
                || str_contains(strtr($docComment, from: "\t", to: ' '), ' null')
            )
        );
    }
}
