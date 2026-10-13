<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;
use function strtolower;

/**
 * Parses names in constant expressions, such as `true`, `FOO`, `Foo::BAR`, and `self::STATUS_*`, and constant arrays
 * with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocConstantNameParser
{
    /**
     * Returns the literal name, or null for an array.
     *
     * @param callable(PhpDocTokens): (string|null) $item
     * @throws PhpDocSyntaxError
     */
    public static function name(PhpDocTokens $tokens, callable $item): string|null
    {
        $identifier = $tokens->value();
        $tokens->next();
        $keyword = strtolower($identifier);
        if (in_array($keyword, ['true', 'false', 'null'], strict: true)) {
            return $keyword;
        }
        if ($keyword === 'array') {
            $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
            self::array($tokens, PhpDocLexer::CLOSE_PARENTHESES, $item);
            return null;
        }
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            return $identifier;
        }
        $tokens->next();
        return $identifier . '::' . self::member($tokens);
    }

    /**
     * Parses array items up to the given closing token.
     *
     * @param callable(PhpDocTokens): (string|null) $item
     * @throws PhpDocSyntaxError
     */
    public static function array(PhpDocTokens $tokens, int $end, callable $item): void
    {
        $more = !$tokens->tryConsume($end);
        while ($more) {
            $item($tokens);
            if ($tokens->is(PhpDocLexer::DOUBLE_ARROW)) {
                $tokens->next();
                $item($tokens);
            }
            $more = $tokens->tryConsume(PhpDocLexer::COMMA) && !$tokens->is($end);
            if (!$more) {
                $tokens->consume($end);
            }
        }
    }

    /**
     * Parses a constant name after `::`, which may alternate name parts and `*` wildcards. Whitespace after a
     * wildcard ends the name.
     *
     * @throws PhpDocSyntaxError
     */
    private static function member(PhpDocTokens $tokens): string
    {
        $name = '';
        $wildcard = null;
        while (true) {
            if ($wildcard !== false && $tokens->is(PhpDocLexer::IDENTIFIER)) {
                $name .= $tokens->value();
                $tokens->next();
                $wildcard = false;
                continue;
            }
            if ($wildcard === true || !$tokens->is(PhpDocLexer::WILDCARD)) {
                break;
            }
            $tokens->next();
            $name .= '*';
            $wildcard = true;
            if ($tokens->precededByWhitespace()) {
                return $name;
            }
        }
        if ($wildcard === null) {
            throw PhpDocSyntaxError::instance();
        }
        return $name;
    }
}
