<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;
use function str_replace;
use function strtolower;

/**
 * Parses constant expressions with phpstan/phpdoc-parser 2.x's grammar and returns literal names such as `'foo'`,
 * `42`, or `Foo::BAR`. Strings are requoted with {@see PhpDocLiteral::quote()}, and numbers lose digit separators.
 *
 * In Doctrine annotations, strings use double quotes and may span lines, and every string reads as an empty string
 * literal.
 *
 * @internal
 */
final class PhpDocConstantParser
{
    public function __construct(
        private readonly PhpDocTokens $tokens,
        private readonly bool $doctrine = false,
    ) {}

    /**
     * Returns the literal name, or null for an array.
     *
     * @throws PhpDocSyntaxError
     */
    public function constant(): string|null
    {
        $string = $this->doctrine ? $this->doctrineString() : $this->string();
        return $string ?? $this->other();
    }

    /**
     * Returns the quote of the current string token, or null if it is no quoted string.
     *
     * @return "'"|'"'|null
     * @mutation-free
     */
    public function quote(): string|null
    {
        return match (true) {
            $this->tokens->is(PhpDocLexer::SINGLE_QUOTED_STRING) => "'",
            $this->tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) => '"',
            default => null,
        };
    }

    /** Returns the string literal, or null if no string follows. */
    private function string(): string|null
    {
        $quote = $this->quote();
        if ($quote === null) {
            return null;
        }
        $value = PhpDocLiteralString::unescape($this->tokens->value());
        $this->tokens->next();
        return PhpDocLiteral::quote($value, $quote);
    }

    /** Skips a string that may consist of several adjacent parts, or returns null if no string follows. */
    private function doctrineString(): string|null
    {
        $tokens = $this->tokens;
        $multiLine = $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
        if (!$multiLine && !$tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING)) {
            return null;
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

    /**
     * Parses a number, a name, or an array.
     *
     * @throws PhpDocSyntaxError
     */
    private function other(): string|null
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::FLOAT) || $tokens->is(PhpDocLexer::INTEGER)) {
            $value = str_replace('_', replace: '', subject: $tokens->value());
            $tokens->next();
            return $value;
        }
        if ($tokens->is(PhpDocLexer::IDENTIFIER)) {
            return $this->name();
        }
        $tokens->consume(PhpDocLexer::OPEN_SQUARE_BRACKET);
        $this->array(PhpDocLexer::CLOSE_SQUARE_BRACKET);
        return null;
    }

    /**
     * Parses a name such as `true`, `FOO`, `Foo::BAR`, or `self::STATUS_*`, or an `array(…)`. Returns null for an array.
     *
     * @throws PhpDocSyntaxError
     */
    private function name(): string|null
    {
        $tokens = $this->tokens;
        $identifier = $tokens->value();
        $tokens->next();
        $keyword = strtolower($identifier);
        if (in_array($keyword, ['true', 'false', 'null'], strict: true)) {
            return $keyword;
        }
        if ($keyword === 'array') {
            $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
            $this->array(PhpDocLexer::CLOSE_PARENTHESES);
            return null;
        }
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            return $identifier;
        }
        $tokens->next();
        return $identifier . '::' . $this->member();
    }

    /**
     * Parses array items up to the given closing token.
     *
     * @throws PhpDocSyntaxError
     */
    private function array(int $end): void
    {
        $tokens = $this->tokens;
        $more = !$tokens->tryConsume($end);
        while ($more) {
            $this->constant();
            if ($tokens->is(PhpDocLexer::DOUBLE_ARROW)) {
                $tokens->next();
                $this->constant();
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
    private function member(): string
    {
        $tokens = $this->tokens;
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
