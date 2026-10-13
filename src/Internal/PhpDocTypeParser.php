<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses PHPDoc types with phpstan/phpdoc-parser 2.x's grammar. The parsers follow phpdoc-parser step by step,
 * because where it backtracks decides which declarations are valid.
 *
 * Parsers return the type, or null for syntax that the decoder does not support, such as `?T`, `T[]`, callables,
 * conditional types, object shapes, and open array shapes.
 *
 * @internal
 */
final class PhpDocTypeParser
{
    /** Parses a standalone type such as `list<int>`. */
    public static function parse(string $source): PhpDocType|null
    {
        $tokens = PhpDocTokens::of($source);
        try {
            $type = self::type($tokens);
        } catch (PhpDocSyntaxError) {
            return null;
        }
        $complete = $tokens->is(PhpDocLexer::END);
        return $complete ? PhpDocTypeTree::limit($type) : null;
    }

    /**
     * Parses a type, where a union or intersection may continue on the next line.
     *
     * @throws PhpDocSyntaxError
     */
    public static function type(PhpDocTokens $tokens): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::NULLABLE)) {
            return PhpDocAtomicTypeParser::nullable($tokens);
        }
        $type = PhpDocAtomicTypeParser::atomic($tokens);
        $compound = self::compoundAcrossLines($tokens, $type);
        return $compound === false ? $type : $compound;
    }

    /**
     * Tries a union or intersection that continues on the next line first, then one on the same line.
     *
     * @throws PhpDocSyntaxError
     */
    private static function compoundAcrossLines(PhpDocTokens $tokens, PhpDocType|null $type): PhpDocType|false|null
    {
        $state = $tokens->state;
        $state->push();
        $state->skipLineEnds();
        try {
            $compound = self::compound($tokens, $type);
        } catch (PhpDocSyntaxError) {
            $compound = false;
        }
        if ($compound !== false) {
            $state->drop();
            return $compound;
        }
        $state->rollback();
        return self::compound($tokens, $type);
    }

    /**
     * Returns false if no union or intersection follows.
     *
     * @throws PhpDocSyntaxError
     */
    private static function compound(PhpDocTokens $tokens, PhpDocType|null $type): PhpDocType|false|null
    {
        $operator = $tokens->is(PhpDocLexer::UNION) ? PhpDocLexer::UNION : PhpDocLexer::INTERSECTION;
        if (!$tokens->is($operator)) {
            return false;
        }
        $types = [$type];
        $continues = true;
        while ($continues) {
            $tokens->next();
            $types[] = PhpDocAtomicTypeParser::atomic($tokens);
            $tokens->state->push();
            $tokens->state->skipLineEnds();
            $continues = $tokens->is($operator);
            if ($continues) {
                $tokens->state->drop();
            }
        }
        $tokens->state->rollback();
        return PhpDocTypeTree::named($operator === PhpDocLexer::UNION ? '|' : '&', $types);
    }
}
