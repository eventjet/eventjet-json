<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses arrays in Doctrine-style annotations, such as `{"a": 1, Foo::BAR = 2}`, like phpstan/phpdoc-parser 2.x.
 *
 * @internal
 */
final class PhpDocDoctrineArrayParser
{
    /**
     * Parses the items after `{` up to the closing brace.
     *
     * @throws PhpDocSyntaxError
     */
    public static function items(PhpDocTokens $tokens): void
    {
        $more = true;
        while ($more && !$tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
            self::item($tokens);
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
        $tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
    }

    /**
     * Parses an item, which may have a key followed by `=` or `:`.
     *
     * @throws PhpDocSyntaxError
     */
    private static function item(PhpDocTokens $tokens): void
    {
        $state = $tokens->state;
        try {
            $state->push();
            self::key($tokens);
            $separated = $tokens->tryConsume(PhpDocLexer::EQUAL);
            if (!$separated) {
                $separated = $tokens->tryConsume(PhpDocLexer::COLON);
            }
            if (!$separated) {
                throw PhpDocSyntaxError::instance();
            }
            PhpDocDoctrineParser::value($tokens);
            $state->drop();
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            PhpDocDoctrineParser::value($tokens);
        }
    }

    /** @throws PhpDocSyntaxError */
    private static function key(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::INTEGER) || $tokens->is(PhpDocLexer::SINGLE_QUOTED_STRING)) {
            $tokens->next();
            return;
        }
        if ($tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING) || $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING)) {
            self::strings($tokens);
            return;
        }
        self::name($tokens);
    }

    /**
     * Parses a key that is a name or a class constant.
     *
     * @throws PhpDocSyntaxError
     */
    private static function name(PhpDocTokens $tokens): void
    {
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            throw PhpDocSyntaxError::instance();
        }
        $state = $tokens->state;
        $state->push();
        $tokens->next();
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            $state->drop();
            return;
        }
        $state->rollback();
        PhpDocConstantParser::doctrineConstant($tokens);
    }

    /** Skips a key of adjacent strings, which may be the parts of a string with doubled quotes. */
    private static function strings(PhpDocTokens $tokens): void
    {
        // phpdoc-parser skips one more token after a key that starts with a multi-line string.
        $extra = $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
        while ($tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING)) {
            $tokens->next();
        }
        if ($extra) {
            $tokens->next();
        }
    }
}
