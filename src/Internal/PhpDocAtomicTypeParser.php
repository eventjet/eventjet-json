<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses a type without a top-level union or intersection, with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocAtomicTypeParser
{
    /** @throws PhpDocSyntaxError */
    public static function atomic(PhpDocTokens $tokens): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            return self::parenthesized($tokens);
        }
        if ($tokens->is(PhpDocLexer::THIS_VARIABLE)) {
            $tokens->next();
            self::arrayOrOffsetAccess($tokens, null);
            $tokens->state->flushComments();
            return null;
        }
        return self::nameOrConstant($tokens);
    }

    /** @throws PhpDocSyntaxError */
    public static function nullable(PhpDocTokens $tokens): null
    {
        $tokens->consume(PhpDocLexer::NULLABLE);
        self::atomic($tokens);
        return null;
    }

    /**
     * Parses a type that starts with a name, or a constant such as `'foo'` or `Foo::BAR`.
     *
     * @throws PhpDocSyntaxError
     */
    private static function nameOrConstant(PhpDocTokens $tokens): PhpDocType|null
    {
        $name = $tokens->value();
        $state = $tokens->state;
        $state->push();
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            $state->drop();
            return self::constant($tokens);
        }
        $tokens->next();
        $state->flushComments();
        if ($tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            $state->rollback();
            return self::constant($tokens);
        }
        $state->drop();
        return PhpDocNamedTypeParser::named($tokens, $name);
    }

    /**
     * Returns the type unchanged if no `[]` or `[K]` follows it, or null for an array or offset access. An invalid
     * access is not parsed.
     */
    public static function arrayOrOffsetAccess(PhpDocTokens $tokens, PhpDocType|null $type): PhpDocType|null
    {
        try {
            while ($tokens->is(PhpDocLexer::OPEN_SQUARE_BRACKET)) {
                self::access($tokens);
                $type = null;
            }
        } catch (PhpDocSyntaxError) {
            $tokens->state->rollback();
        }
        return $type;
    }

    /**
     * Parses `[]` or `[K]`. The save point stays on the stack if parsing fails.
     *
     * @throws PhpDocSyntaxError
     */
    private static function access(PhpDocTokens $tokens): void
    {
        $tokens->state->push();
        $offsetAccess = !$tokens->precededByWhitespace();
        $tokens->next();
        if ($offsetAccess && !$tokens->is(PhpDocLexer::CLOSE_SQUARE_BRACKET)) {
            PhpDocTypeParser::type($tokens);
        }
        $tokens->consume(PhpDocLexer::CLOSE_SQUARE_BRACKET);
        $tokens->state->drop();
    }

    /** @throws PhpDocSyntaxError */
    private static function parenthesized(PhpDocTokens $tokens): PhpDocType|null
    {
        $tokens->state->skipLineEnds();
        $type = PhpDocNestedTypeParser::nested($tokens);
        $tokens->state->skipLineEnds();
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        $type = self::arrayOrOffsetAccess($tokens, $type);
        $tokens->state->flushComments();
        return $type;
    }

    /** @throws PhpDocSyntaxError */
    private static function constant(PhpDocTokens $tokens): PhpDocType|null
    {
        $name = PhpDocConstantParser::constant($tokens);
        if ($name === null) {
            throw PhpDocSyntaxError::instance();
        }
        $tokens->state->flushComments();
        return self::arrayOrOffsetAccess($tokens, new PhpDocType($name));
    }
}
