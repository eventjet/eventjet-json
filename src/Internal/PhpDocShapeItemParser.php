<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses array shape entries and object shapes with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocShapeItemParser
{
    /**
     * Returns the entry, or null if the decoder does not support its type. An entry without a valid key is parsed
     * again as a type.
     *
     * @throws PhpDocSyntaxError
     */
    public static function item(PhpDocTokens $tokens): PhpDocTupleEntry|null
    {
        $state = $tokens->state;
        try {
            $state->push();
            $key = self::key($tokens);
            $optional = $tokens->tryConsume(PhpDocLexer::NULLABLE);
            $tokens->consume(PhpDocLexer::COLON);
            $type = PhpDocTypeParser::type($tokens);
            $state->drop();
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            $key = null;
            $optional = false;
            $type = PhpDocTypeParser::type($tokens);
        }
        return $type === null ? null : new PhpDocTupleEntry($type, $key, $optional);
    }

    /**
     * Parses an object shape, which the decoder does not support.
     *
     * @throws PhpDocSyntaxError
     */
    public static function objectShape(PhpDocTokens $tokens): null
    {
        $state = $tokens->state;
        $tokens->consume(PhpDocLexer::OPEN_CURLY_BRACKET);
        $more = true;
        while ($more) {
            $state->skipLineEnds();
            if ($tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
                $tokens->next();
                return null;
            }
            self::objectItem($tokens);
            $state->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
        $tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private static function key(PhpDocTokens $tokens): PhpDocType
    {
        $value = $tokens->value();
        if ($tokens->is(PhpDocLexer::INTEGER)) {
            return PhpDocConstantParser::number($tokens);
        }
        $quoted = PhpDocConstantParser::isQuoted($tokens);
        if ($quoted) {
            $tokens->next();
            return PhpDocType::literal(PhpDocLiteral::quoted($value));
        }
        return new PhpDocType(self::name($tokens, $value));
    }

    /**
     * Parses a key that is a name or a class constant.
     *
     * @throws PhpDocSyntaxError
     */
    private static function name(PhpDocTokens $tokens, string $name): string
    {
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            return $name;
        }
        $tokens->next();
        $member = $tokens->value();
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        return $name . '::' . $member;
    }

    /** @throws PhpDocSyntaxError */
    private static function objectItem(PhpDocTokens $tokens): void
    {
        $quoted = PhpDocConstantParser::isQuoted($tokens);
        if ($quoted) {
            $tokens->next();
        }
        if (!$quoted) {
            $tokens->consume(PhpDocLexer::IDENTIFIER);
        }
        $tokens->state->flushComments();
        $tokens->tryConsume(PhpDocLexer::NULLABLE);
        $tokens->consume(PhpDocLexer::COLON);
        PhpDocTypeParser::type($tokens);
    }
}
