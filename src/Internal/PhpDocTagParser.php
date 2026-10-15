<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Reads the `@param` and `@var` tags of a doc comment, including their `@phan-`, `@psalm-`, and `@phpstan-` variants,
 * like phpstan/phpdoc-parser 2.x. All other tags are parsed too, because where they end decides where the next tag
 * starts.
 *
 * @internal
 */
final class PhpDocTagParser
{
    public const int TEXT = 0;

    /** A tag that phpdoc-parser does not know, which the next tag may follow on the same line. */
    public const int GENERIC = 1;

    public const int INVALID = 2;
    public const int VALID = 3;

    /**
     * Returns the `@param` and `@var` tags in order. A doc comment that phpdoc-parser cannot parse as a whole has no
     * tags.
     *
     * @return list<PhpDocTag>
     */
    public static function tags(string $doc): array
    {
        $tokens = PhpDocTokens::of($doc);
        try {
            return self::children($tokens);
        } catch (PhpDocSyntaxError) {
            return [];
        }
    }

    /**
     * Parses a tag value after its name and returns its kind, and the tag if it is a `@param` or `@var` tag.
     *
     * @return array{int, PhpDocTag|null}
     */
    public static function tagValue(PhpDocTokens $tokens, string $tag): array
    {
        $state = $tokens->state;
        try {
            $state->push();
            $value = PhpDocTagValues::parse($tokens, $tag);
            $state->drop();
            return $value;
        } catch (PhpDocSyntaxError) {
            $state->rollback();
            PhpDocTextParser::text($tokens);
            return [self::INVALID, null];
        }
    }

    /**
     * @return list<PhpDocTag>
     * @throws PhpDocSyntaxError
     */
    private static function children(PhpDocTokens $tokens): array
    {
        $tokens->consume(PhpDocLexer::OPEN_PHPDOC);
        $tags = [];
        $separated = true;
        while ($separated && !$tokens->is(PhpDocLexer::CLOSE_PHPDOC)) {
            [$kind, $tag] = self::child($tokens);
            if ($tag !== null) {
                $tags[] = $tag;
            }
            $lineEnd = $tokens->tryConsume(PhpDocLexer::PHPDOC_EOL);
            $separated = $lineEnd || $kind === self::GENERIC;
        }
        // phpdoc-parser replaces all tags with one invalid tag if the comment does not end here.
        $complete = $tokens->is(PhpDocLexer::CLOSE_PHPDOC) && !$tokens->state->hasComments();
        return $complete ? $tags : [];
    }

    /**
     * @return array{int, PhpDocTag|null}
     * @throws PhpDocSyntaxError
     */
    private static function child(PhpDocTokens $tokens): array
    {
        $name = $tokens->value();
        if ($tokens->is(PhpDocLexer::PHPDOC_TAG)) {
            $tokens->next();
            return self::tagValue($tokens, $name);
        }
        if ($tokens->is(PhpDocLexer::DOCTRINE_TAG)) {
            $tokens->next();
            PhpDocDoctrineParser::annotation($tokens);
            return [self::GENERIC, null];
        }
        PhpDocTextParser::text($tokens);
        return [self::TEXT, null];
    }
}
