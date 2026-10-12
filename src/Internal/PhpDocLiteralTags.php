<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionProperty;

use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

/** @internal */
final class PhpDocLiteralTags
{
    /** @return array<string, bool> */
    public static function parameters(string $docComment): array
    {
        $tags = [];
        preg_match_all(
            '~@param[ \t]+([^\r\n*]+?)[ \t]+(?:&|\.\.\.)?\$([A-Za-z_][A-Za-z0-9_]*)(?:[ \t]|\r?\n|$)~',
            $docComment,
            $tags,
            PREG_SET_ORDER,
        );
        /** @var list<array{string, string, string}> $tags */
        $literals = [];
        foreach ($tags as [, $type, $name]) {
            $literals[$name] = self::isLiteral($type);
        }
        return $literals;
    }

    public static function property(ReflectionProperty $property, string $docComment): bool
    {
        $var = [];
        preg_match('~@var[ \t]+([^\r\n*]+)~', $docComment, $var);
        $literal = self::isLiteral($var[1] ?? '');
        return $literal && PhpDocLiteralFieldType::accepts($property->getType());
    }

    public static function isLiteral(string $type): bool
    {
        return preg_match('~[\'"0-9]|::|\b(?:true|false)\b|\A\s*null\s*\z~', $type) === 1;
    }
}
