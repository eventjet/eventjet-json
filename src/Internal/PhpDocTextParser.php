<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;

/**
 * Skips text in a doc comment. phpdoc-parser 2.x continues text on later lines until a line starts with a tag, but
 * those lines are text on their own as well, so skipping one line at a time reads the same tags.
 *
 * @internal
 */
final class PhpDocTextParser
{
    private const array LINE_ENDS = [PhpDocLexer::PHPDOC_EOL, PhpDocLexer::CLOSE_PHPDOC, PhpDocLexer::END];

    /** Skips the rest of the line. */
    public static function text(PhpDocTokens $tokens): void
    {
        self::skip($tokens, self::LINE_ENDS);
    }

    /**
     * Skips tokens on the current line up to one of the given types.
     *
     * @param list<int> $stops
     */
    public static function skip(PhpDocTokens $tokens, array $stops): void
    {
        while (!in_array($tokens->types[$tokens->index] ?? PhpDocLexer::END, $stops, strict: true)) {
            $tokens->index++;
        }
    }
}
