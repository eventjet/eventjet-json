<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses generic arguments such as `<string, int>` and template declarations with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocGenericParser
{
    /**
     * Parses the generic arguments of the given type name, starting at `<`. The type is null if an argument has a
     * variance or is the `*` wildcard.
     *
     * @throws PhpDocSyntaxError
     */
    public static function generic(PhpDocTokens $tokens, string $name): PhpDocType|null
    {
        $state = $tokens->state;
        $tokens->consume(PhpDocLexer::OPEN_ANGLE_BRACKET);
        $state->skipLineEnds();
        $arguments = [];
        $more = true;
        while ($more) {
            $arguments[] = self::argument($tokens);
            $state->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
            $state->skipLineEnds();
            $more = $more && !$tokens->is(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        }
        $state->flushComments();
        $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        return PhpDocTypeTree::named($name, $arguments);
    }

    /**
     * Parses a template such as `T of Foo = Bar`, without a description.
     *
     * @throws PhpDocSyntaxError
     */
    public static function template(PhpDocTokens $tokens): void
    {
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        foreach ([['of', 'as'], ['super'], ['=']] as $keywords) {
            $bound = self::keyword($tokens, $keywords);
            if ($bound) {
                PhpDocTypeParser::type($tokens);
            }
        }
    }

    /** @throws PhpDocSyntaxError */
    private static function argument(PhpDocTokens $tokens): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::WILDCARD)) {
            $tokens->next();
            return null;
        }
        $variance = self::keyword($tokens, ['contravariant', 'covariant']);
        $type = PhpDocTypeParser::type($tokens);
        return $variance ? null : $type;
    }

    /**
     * Consumes the first of the given keywords that follows.
     *
     * @param non-empty-list<string> $keywords
     */
    private static function keyword(PhpDocTokens $tokens, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (!$tokens->isValue($keyword)) {
                continue;
            }
            $tokens->next();
            return true;
        }
        return false;
    }
}
