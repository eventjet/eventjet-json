<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Skips tag descriptions like phpstan/phpdoc-parser 2.x.
 *
 * @internal
 */
final class PhpDocDescriptionParser
{
    private const array GENERIC_STOPS = [
        PhpDocLexer::PHPDOC_TAG,
        PhpDocLexer::DOCTRINE_TAG,
        PhpDocLexer::PHPDOC_EOL,
        PhpDocLexer::CLOSE_PHPDOC,
        PhpDocLexer::END,
    ];

    /**
     * Skips the description after a type, which must be separated by whitespace and must not start with `|` or `&`, so
     * that the type cannot continue into it.
     *
     * @throws PhpDocSyntaxError
     */
    public static function limited(PhpDocTokens $tokens): void
    {
        $continuesType = $tokens->is(PhpDocLexer::UNION) || $tokens->is(PhpDocLexer::INTERSECTION);
        $ends =
            $tokens->is(PhpDocLexer::PHPDOC_EOL)
            || $tokens->is(PhpDocLexer::CLOSE_PHPDOC)
            || $tokens->is(PhpDocLexer::END);
        if ($continuesType || !$ends && !$tokens->precededByWhitespace()) {
            throw PhpDocSyntaxError::instance();
        }
        PhpDocTextParser::text($tokens);
    }

    /**
     * Skips the description of a tag that phpdoc-parser does not know. It ends at a later tag on the same line that is
     * unknown as well.
     */
    public static function generic(PhpDocTokens $tokens): void
    {
        PhpDocTextParser::skip($tokens, self::GENERIC_STOPS);
        $ends = self::endsAtInlineTag($tokens);
        if (!$ends) {
            PhpDocTextParser::text($tokens);
        }
    }

    /**
     * Whether the description ends at the current tag, which is the case for a Doctrine annotation or a tag that
     * phpdoc-parser does not know. phpdoc-parser also ends it before an invalid tag followed by `(`, which then reads
     * the same as a description. At the end of a line, the result does not matter.
     */
    private static function endsAtInlineTag(PhpDocTokens $tokens): bool
    {
        if (!$tokens->precededByWhitespace()) {
            return false;
        }
        if (!$tokens->is(PhpDocLexer::PHPDOC_TAG)) {
            return true;
        }
        $state = $tokens->state;
        $state->push();
        $name = $tokens->value();
        $tokens->next();
        $value = PhpDocTagParser::tagValue($tokens, $name);
        $state->rollback();
        return $value === PhpDocChildKind::Generic;
    }
}
