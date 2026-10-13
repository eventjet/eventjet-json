<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses Doctrine-style annotations such as `@ORM\Column(name="id")` like phpstan/phpdoc-parser 2.x. Their arguments
 * may span lines, so they decide where the next tag starts.
 *
 * @internal
 */
final class PhpDocDoctrineParser
{
    /**
     * Parses the arguments and the description of an annotation after its name.
     *
     * @throws PhpDocSyntaxError
     */
    public static function annotation(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->lineEndsAreWhitespace = true;
            try {
                $tokens->next();
                self::arguments($tokens);
            } finally {
                $tokens->lineEndsAreWhitespace = false;
            }
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        }
        PhpDocDescriptionParser::generic($tokens);
    }

    /** @throws PhpDocSyntaxError */
    public static function value(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::OPEN_CURLY_BRACKET)) {
            $tokens->next();
            PhpDocDoctrineArrayParser::items($tokens);
            return;
        }
        $annotation = self::nested($tokens);
        if (!$annotation) {
            self::scalar($tokens);
        }
    }

    /**
     * Parses a nested annotation with its optional arguments, where line ends are skipped as in the outer annotation.
     * Returns false if no annotation starts here.
     *
     * @throws PhpDocSyntaxError
     */
    private static function nested(PhpDocTokens $tokens): bool
    {
        if (!$tokens->is(PhpDocLexer::PHPDOC_TAG) && !$tokens->is(PhpDocLexer::DOCTRINE_TAG)) {
            return false;
        }
        $tokens->next();
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            self::arguments($tokens);
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        }
        return true;
    }

    /**
     * Parses a name, or a constant expression other than an array.
     *
     * @throws PhpDocSyntaxError
     */
    private static function scalar(PhpDocTokens $tokens): void
    {
        $state = $tokens->state;
        $state->push();
        $name = $tokens->tryConsume(PhpDocLexer::IDENTIFIER) && !$tokens->is(PhpDocLexer::DOUBLE_COLON);
        if ($name) {
            $state->drop();
            return;
        }
        $state->rollback();
        $constant = PhpDocConstantParser::doctrineConstant($tokens);
        if ($constant === null) {
            throw PhpDocSyntaxError::instance();
        }
    }

    /**
     * Parses the arguments after `(` up to the closing parenthesis.
     *
     * @throws PhpDocSyntaxError
     */
    private static function arguments(PhpDocTokens $tokens): void
    {
        $more = true;
        while ($more && !$tokens->is(PhpDocLexer::CLOSE_PARENTHESES)) {
            self::argument($tokens);
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
    }

    /**
     * Parses an argument, which may be named with `name=`.
     *
     * @throws PhpDocSyntaxError
     */
    private static function argument(PhpDocTokens $tokens): void
    {
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            self::value($tokens);
            return;
        }
        $state = $tokens->state;
        try {
            $state->push();
            $tokens->next();
            $tokens->consume(PhpDocLexer::EQUAL);
            self::value($tokens);
            $state->drop();
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            self::value($tokens);
        }
    }
}
