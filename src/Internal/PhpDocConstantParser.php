<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function str_replace;

/**
 * Parses constant expressions with phpstan/phpdoc-parser 2.x's grammar and returns them as types: literals such as
 * `'foo'` or `42`, and names such as `true` or `Foo::BAR`. A number that PHP and PHPStan read differently is no literal,
 * and its type records it as unsupported.
 *
 * @internal
 */
final class PhpDocConstantParser
{
    /**
     * Returns the type of the constant, or null for an array.
     *
     * @throws PhpDocSyntaxError
     */
    public static function constant(PhpDocTokens $tokens): PhpDocType|null
    {
        $quoted = self::isQuoted($tokens);
        if (!$quoted) {
            return self::other($tokens, self::constant(...));
        }
        $literal = PhpDocLiteral::quoted($tokens->value());
        $tokens->next();
        return PhpDocType::literal($literal);
    }

    /**
     * Parses a constant expression in a Doctrine annotation, where strings use double quotes and may span lines.
     * Returns whether it is no array.
     *
     * @throws PhpDocSyntaxError
     */
    public static function doctrineConstant(PhpDocTokens $tokens): bool
    {
        $string =
            $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING) || $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING);
        if (!$string) {
            return self::other($tokens, self::doctrineConstant(...)) !== null;
        }
        self::doctrineString($tokens);
        return true;
    }

    /** Skips a Doctrine string, which may be a single-line string followed by more strings. */
    private static function doctrineString(PhpDocTokens $tokens): void
    {
        $multiLine = $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
        $tokens->next();
        while (
            !$multiLine
            && ($tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING))
        ) {
            $tokens->next();
        }
    }

    /**
     * Reads a number token. A number that PHP and PHPStan can read differently is no literal, and its type records it
     * as unsupported.
     */
    public static function number(PhpDocTokens $tokens): PhpDocType
    {
        $number = str_replace('_', replace: '', subject: $tokens->value());
        $literal = $tokens->is(PhpDocLexer::FLOAT) ? PhpDocLiteral::float($number) : PhpDocLiteral::integer($number);
        $tokens->next();
        return $literal === null ? new PhpDocType($number, unsupportedNumber: $number) : PhpDocType::literal($literal);
    }

    public static function isQuoted(PhpDocTokens $tokens): bool
    {
        return $tokens->is(PhpDocLexer::SINGLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING);
    }

    /**
     * Parses a number, a name, or an array, whose items are parsed with the given parser.
     *
     * @param callable(PhpDocTokens): mixed $item
     * @throws PhpDocSyntaxError
     */
    private static function other(PhpDocTokens $tokens, callable $item): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::FLOAT) || $tokens->is(PhpDocLexer::INTEGER)) {
            return self::number($tokens);
        }
        if ($tokens->is(PhpDocLexer::IDENTIFIER)) {
            $name = PhpDocConstantNameParser::name($tokens, $item);
            return $name === null ? null : new PhpDocType($name);
        }
        $tokens->consume(PhpDocLexer::OPEN_SQUARE_BRACKET);
        PhpDocConstantNameParser::array($tokens, PhpDocLexer::CLOSE_SQUARE_BRACKET, $item);
        return null;
    }
}
