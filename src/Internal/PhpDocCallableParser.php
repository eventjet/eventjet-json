<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses callable types such as `callable(int): string` with phpstan/phpdoc-parser 2.x's grammar. The decoder does not
 * support callables, but parsing them decides where a declaration ends.
 *
 * @internal
 */
final class PhpDocCallableParser
{
    /** Parses a callable after its name if possible, and otherwise restores the position. */
    public static function attempt(PhpDocTokens $tokens): bool
    {
        $state = $tokens->state;
        try {
            $state->push();
            self::callable($tokens);
            $state->drop();
            return true;
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            return false;
        }
    }

    /** @throws PhpDocSyntaxError */
    private static function callable(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            self::templates($tokens);
        }
        $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
        $tokens->state->skipLineEnds();
        if (!$tokens->is(PhpDocLexer::CLOSE_PARENTHESES)) {
            self::parameters($tokens);
        }
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        $tokens->consume(PhpDocLexer::COLON);
        PhpDocCallableReturnParser::returnType($tokens);
    }

    /** @throws PhpDocSyntaxError */
    private static function templates(PhpDocTokens $tokens): void
    {
        $state = $tokens->state;
        $tokens->next();
        $more = true;
        while ($more) {
            $state->skipLineEnds();
            PhpDocGenericParser::template($tokens);
            $state->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
            $state->skipLineEnds();
            $more = $more && !$tokens->is(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        }
        $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
    }

    /**
     * Parses one or more parameters, which may end with a comma.
     *
     * @throws PhpDocSyntaxError
     */
    private static function parameters(PhpDocTokens $tokens): void
    {
        $state = $tokens->state;
        $more = true;
        while ($more) {
            self::parameter($tokens);
            $state->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
            $state->skipLineEnds();
            $more = $more && !$tokens->is(PhpDocLexer::CLOSE_PARENTHESES);
        }
    }

    /** @throws PhpDocSyntaxError */
    private static function parameter(PhpDocTokens $tokens): void
    {
        PhpDocTypeParser::type($tokens);
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $tokens->tryConsume(PhpDocLexer::VARIABLE);
        $tokens->tryConsume(PhpDocLexer::EQUAL);
    }
}
