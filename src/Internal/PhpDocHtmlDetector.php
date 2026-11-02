<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function str_contains;
use function strlen;
use function substr_compare;

/**
 * Detects an HTML element such as `<p>…</p>` after a type name, which phpstan/phpdoc-parser 2.x does not read as
 * generic arguments.
 *
 * @internal
 */
final class PhpDocHtmlDetector
{
    /** Whether an element starts at the current `<` and its end tag follows later. Keeps the position. */
    public static function follows(PhpDocTokens $tokens): bool
    {
        $tokens->state->push();
        $tokens->next();
        $html = self::element($tokens);
        $tokens->state->rollback();
        return $html;
    }

    private static function element(PhpDocTokens $tokens): bool
    {
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            return false;
        }
        $tag = $tokens->value();
        $tokens->next();
        if (!$tokens->is(PhpDocLexer::CLOSE_ANGLE_BRACKET)) {
            return false;
        }
        while (!$tokens->is(PhpDocLexer::END)) {
            $closes = self::closes($tokens, $tag);
            if ($closes) {
                return true;
            }
            $tokens->next();
        }
        return false;
    }

    private static function closes(PhpDocTokens $tokens, string $tag): bool
    {
        $end = '</' . $tag . '>';
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $tokens->next();
            if (str_contains($tokens->value(), '/' . $tag . '>')) {
                return true;
            }
        }
        return substr_compare($tokens->value(), $end, -strlen($end)) === 0;
    }
}
