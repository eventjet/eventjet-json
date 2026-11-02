<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses array shapes such as `array{0: int, 1?: string}` with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocArrayShapeParser
{
    /**
     * Returns the shape, or null for an open shape or an entry the decoder does not support.
     *
     * @throws PhpDocSyntaxError
     */
    public static function shape(PhpDocTokens $tokens, string $kind): PhpDocType|null
    {
        $state = $tokens->state;
        $tokens->consume(PhpDocLexer::OPEN_CURLY_BRACKET);
        $entries = [];
        $more = true;
        while ($more) {
            $state->skipLineEnds();
            if ($tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
                $tokens->next();
                return PhpDocTypeTree::shape($kind, $entries);
            }
            if ($tokens->is(PhpDocLexer::VARIADIC)) {
                $tokens->next();
                self::unsealed($tokens, $kind);
                return self::close($tokens, null);
            }
            $entries[] = PhpDocShapeItemParser::item($tokens);
            $state->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
            if ($tokens->is(PhpDocLexer::COMMENT)) {
                $tokens->next();
            }
        }
        return self::close($tokens, PhpDocTypeTree::shape($kind, $entries));
    }

    /** @throws PhpDocSyntaxError */
    private static function close(PhpDocTokens $tokens, PhpDocType|null $shape): PhpDocType|null
    {
        $tokens->state->skipLineEnds();
        $tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
        return $shape;
    }

    /**
     * Parses the rest of an open shape after `...`, such as `<int, string>` for `array` or `<string>` for lists.
     *
     * @throws PhpDocSyntaxError
     */
    private static function unsealed(PhpDocTokens $tokens, string $kind): void
    {
        $state = $tokens->state;
        $state->skipLineEnds();
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $tokens->next();
            $state->skipLineEnds();
            PhpDocTypeParser::type($tokens);
            $state->skipLineEnds();
            $keyed = $kind === 'array' && $tokens->tryConsume(PhpDocLexer::COMMA);
            if ($keyed) {
                $state->skipLineEnds();
                PhpDocTypeParser::type($tokens);
                $state->skipLineEnds();
            }
            $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
            $state->skipLineEnds();
        }
        $tokens->tryConsume(PhpDocLexer::COMMA);
    }
}
