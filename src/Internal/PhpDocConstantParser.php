<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function str_replace;

/**
 * Parses constant expressions with phpstan/phpdoc-parser 2.x's grammar and returns literal names such as `'foo'`,
 * `42`, or `Foo::BAR`. Strings are requoted with {@see PhpDocLiteral::quote()}, and numbers lose digit separators.
 *
 * @internal
 */
final class PhpDocConstantParser
{
    /**
     * Returns the literal name, or null for an array.
     *
     * @throws PhpDocSyntaxError
     */
    public static function constant(PhpDocTokens $tokens): string|null
    {
        $quote = self::quote($tokens);
        if ($quote === null) {
            return self::other($tokens, self::constant(...));
        }
        $value = PhpDocLiteralString::unescape($tokens->value());
        $tokens->next();
        return PhpDocLiteral::quote($value, $quote);
    }

    /**
     * Parses a constant expression in a Doctrine annotation, where strings use double quotes and may span lines.
     * Returns null for an array, and an empty string literal for any string.
     *
     * @throws PhpDocSyntaxError
     */
    public static function doctrineConstant(PhpDocTokens $tokens): string|null
    {
        $multiLine = $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
        if (!$multiLine && !$tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING)) {
            return self::other($tokens, self::doctrineConstant(...));
        }
        $tokens->next();
        while (
            !$multiLine
            && ($tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING))
        ) {
            $tokens->next();
        }
        return '""';
    }

    /** @return "'"|'"'|null */
    public static function quote(PhpDocTokens $tokens): string|null
    {
        return match (true) {
            $tokens->is(PhpDocLexer::SINGLE_QUOTED_STRING) => "'",
            $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) => '"',
            default => null,
        };
    }

    /**
     * Parses a number, a name, or an array, whose items are parsed with the given parser.
     *
     * @param callable(PhpDocTokens): (string|null) $item
     * @throws PhpDocSyntaxError
     */
    private static function other(PhpDocTokens $tokens, callable $item): string|null
    {
        if ($tokens->is(PhpDocLexer::FLOAT) || $tokens->is(PhpDocLexer::INTEGER)) {
            $value = str_replace('_', replace: '', subject: $tokens->value());
            $tokens->next();
            return $value;
        }
        if ($tokens->is(PhpDocLexer::IDENTIFIER)) {
            return PhpDocConstantNameParser::name($tokens, $item);
        }
        $tokens->consume(PhpDocLexer::OPEN_SQUARE_BRACKET);
        PhpDocConstantNameParser::array($tokens, PhpDocLexer::CLOSE_SQUARE_BRACKET, $item);
        return null;
    }
}
