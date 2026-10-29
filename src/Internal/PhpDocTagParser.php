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
     * Parses a tag value after its name. Returns the tag if it is a `@param` or `@var` tag that PHPStan reads, and the
     * kind of tag otherwise.
     */
    public static function tagValue(PhpDocTokens $tokens, string $tag): PhpDocTag|PhpDocChildKind
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
            return PhpDocChildKind::Invalid;
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
            $child = self::child($tokens);
            if ($child instanceof PhpDocTag) {
                $tags[] = $child;
            }
            $lineEnd = $tokens->tryConsume(PhpDocLexer::PHPDOC_EOL);
            $separated = $lineEnd || $child === PhpDocChildKind::Generic;
        }
        // phpdoc-parser replaces all tags with one invalid tag if the comment does not end here.
        $complete = $tokens->is(PhpDocLexer::CLOSE_PHPDOC) && !$tokens->state->hasComments();
        return $complete ? $tags : [];
    }

    /** @throws PhpDocSyntaxError */
    private static function child(PhpDocTokens $tokens): PhpDocTag|PhpDocChildKind
    {
        $name = $tokens->value();
        if ($tokens->is(PhpDocLexer::PHPDOC_TAG)) {
            $tokens->next();
            return self::tagValue($tokens, $name);
        }
        if ($tokens->is(PhpDocLexer::DOCTRINE_TAG)) {
            $tokens->next();
            PhpDocDoctrineParser::annotation($tokens);
            return PhpDocChildKind::Generic;
        }
        PhpDocTextParser::text($tokens);
        return PhpDocChildKind::Text;
    }
}
