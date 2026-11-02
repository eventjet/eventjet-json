<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses the values of `@param` and `@var` tags, including prefixed variants, with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocFieldTagValue
{
    /**
     * Returns the tag, or null for a `@param` tag without a type, which PHPStan does not read.
     *
     * @throws PhpDocSyntaxError
     */
    public static function param(PhpDocTokens $tokens, string $tag): PhpDocTag|null
    {
        $typed = !$tokens->is(PhpDocLexer::VARIABLE);
        $type = $typed ? PhpDocTypeTree::limit(PhpDocTypeParser::type($tokens)) : null;
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $variable = $tokens->value();
        $tokens->consume(PhpDocLexer::VARIABLE);
        PhpDocTextParser::text($tokens);
        return $typed ? new PhpDocTag($tag, $variable, $type) : null;
    }

    /**
     * Returns the tag with its variable name, which is empty if the tag has none.
     *
     * @throws PhpDocSyntaxError
     */
    public static function variable(PhpDocTokens $tokens, string $tag): PhpDocTag
    {
        $type = PhpDocTypeTree::limit(PhpDocTypeParser::type($tokens));
        $variable = match (true) {
            $tokens->is(PhpDocLexer::VARIABLE) => $tokens->value(),
            $tokens->is(PhpDocLexer::THIS_VARIABLE) => '$this',
            default => '',
        };
        if ($variable === '') {
            PhpDocDescriptionParser::limited($tokens);
        }
        PhpDocTextParser::text($tokens);
        return new PhpDocTag($tag, $variable, $type);
    }
}
