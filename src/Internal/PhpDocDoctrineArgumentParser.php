<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses arguments of Doctrine-style annotations, which may be named with `name=` or `"name"=`, like
 * phpstan/phpdoc-parser 2.x.
 *
 * @internal
 */
final class PhpDocDoctrineArgumentParser
{
    /** @throws PhpDocSyntaxError */
    public static function argument(PhpDocTokens $tokens): void
    {
        $string = self::string($tokens);
        $named = ($string || $tokens->is(PhpDocLexer::IDENTIFIER)) && self::named($tokens, $string);
        if (!$named) {
            PhpDocDoctrineParser::value($tokens);
        }
    }

    /** Parses an argument with a name. Returns false and keeps the position if the argument has none. */
    private static function named(PhpDocTokens $tokens, bool $string): bool
    {
        $state = $tokens->state;
        try {
            $state->push();
            self::name($tokens, $string);
            PhpDocDoctrineParser::value($tokens);
            $state->drop();
            return true;
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            return false;
        }
    }

    /**
     * Skips a name and the `=` after it.
     *
     * @throws PhpDocSyntaxError
     */
    private static function name(PhpDocTokens $tokens, bool $string): void
    {
        $tokens->next();
        while ($string && self::string($tokens)) {
            $tokens->next();
        }
        $tokens->consume(PhpDocLexer::EQUAL);
    }

    /**
     * Whether a double-quoted string starts here, which the lexer may split into several adjacent tokens.
     *
     * @mutation-free
     */
    private static function string(PhpDocTokens $tokens): bool
    {
        return $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
    }
}
