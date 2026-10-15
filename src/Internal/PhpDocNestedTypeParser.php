<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses a type inside parentheses with phpstan/phpdoc-parser 2.x's grammar, where unions and intersections may span
 * lines and conditional types are allowed.
 *
 * @internal
 */
final class PhpDocNestedTypeParser
{
    /** @throws PhpDocSyntaxError */
    public static function nested(PhpDocTokens $tokens): PhpDocType|null
    {
        $type = self::unflushed($tokens);
        $tokens->state->flushComments();
        return $type;
    }

    /** @throws PhpDocSyntaxError */
    private static function unflushed(PhpDocTokens $tokens): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::NULLABLE)) {
            return PhpDocAtomicTypeParser::nullable($tokens);
        }
        if ($tokens->is(PhpDocLexer::VARIABLE)) {
            $tokens->next();
            if (!$tokens->isValue('is')) {
                throw PhpDocSyntaxError::instance();
            }
            return self::conditional($tokens);
        }
        $type = PhpDocAtomicTypeParser::atomic($tokens);
        if ($tokens->isValue('is')) {
            return self::conditional($tokens);
        }
        $tokens->state->skipLineEnds();
        return self::compound($tokens, $type);
    }

    /**
     * Parses a conditional type from `is` on, such as `is int ? A : B`.
     *
     * @throws PhpDocSyntaxError
     */
    private static function conditional(PhpDocTokens $tokens): null
    {
        $state = $tokens->state;
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if ($tokens->isValue('not')) {
            $tokens->consume(PhpDocLexer::IDENTIFIER);
        }
        PhpDocTypeParser::type($tokens);
        $state->skipLineEnds();
        $tokens->consume(PhpDocLexer::NULLABLE);
        $state->skipLineEnds();
        PhpDocTypeParser::type($tokens);
        $state->skipLineEnds();
        $tokens->consume(PhpDocLexer::COLON);
        $state->skipLineEnds();
        self::nested($tokens);
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private static function compound(PhpDocTokens $tokens, PhpDocType|null $type): PhpDocType|null
    {
        $operator = $tokens->is(PhpDocLexer::UNION) ? PhpDocLexer::UNION : PhpDocLexer::INTERSECTION;
        if (!$tokens->is($operator)) {
            return $type;
        }
        $types = [$type];
        while ($tokens->is($operator)) {
            $tokens->next();
            $tokens->state->skipLineEnds();
            $types[] = PhpDocAtomicTypeParser::atomic($tokens);
            $tokens->state->skipLineEnds();
        }
        return PhpDocTypeTree::named($operator === PhpDocLexer::UNION ? '|' : '&', $types);
    }
}
