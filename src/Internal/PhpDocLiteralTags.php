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
    /** @return array<string, class-string<ConstructorParameter>> */
    public static function parameters(string $docComment): array
    {
        $tags = [];
        preg_match_all(
            '~@param[ \t]+((?:[^\r\n*]|\*(?!/))+?)[ \t]+(?:&|\.\.\.)?\$([A-Za-z_][A-Za-z0-9_]*)(?:[ \t]|\r?\n|$)~',
            $docComment,
            $tags,
            PREG_SET_ORDER,
        );
        /** @var list<array{string, string, string}> $tags */
        $literals = [];
        foreach ($tags as [, $type, $name]) {
            $literal = self::isLiteral($type);
            $literals[$name] = $literal ? PhpDocLiteralParameter::class : ConstructorParameter::class;
        }
        return $literals;
    }

    public static function property(ReflectionProperty $property, string $docComment): bool
    {
        $var = [];
        preg_match('~@var[ \t]+((?:[^\r\n*]|\*(?!/))+)~', $docComment, $var);
        $literal = self::isLiteral($var[1] ?? '');
        return $literal && PhpDocLiteralFieldType::accepts($property->getType());
    }

    /**
     * Quoted literals may contain "@", so each source extends to the end and the parser finds where the type ends.
     *
     * @param non-empty-string $pattern
     * @return non-empty-string
     */
    public static function quotedPattern(string $pattern): string
    {
        return $pattern === '/@param\s+([^@]+)/' ? '/@param\s+(?=([\s\S]*))/' : '/@var\s+(?=([\s\S]*))/';
    }

    public static function isLiteral(string $type): bool
    {
        return preg_match('~[\'"0-9]|::|\b(?:true|false)\b|\A\s*null\s*\z~', $type) === 1;
    }
}
