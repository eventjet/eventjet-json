<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses the values of tags other than `@param`, `@var`, and `@method` with phpstan/phpdoc-parser 2.x's grammar. Their
 * values are not used, but parsing them decides where the next tag starts.
 *
 * @internal
 */
final class PhpDocOtherTagValue
{
    /**
     * Parses a type with a variable, such as for `@property`.
     *
     * @throws PhpDocSyntaxError
     */
    public static function typedVariable(PhpDocTokens $tokens): null
    {
        PhpDocTypeParser::type($tokens);
        $tokens->consume(PhpDocLexer::VARIABLE);
        PhpDocTextParser::text($tokens);
        return null;
    }

    /**
     * Parses a type without a variable, such as for `@return`.
     *
     * @throws PhpDocSyntaxError
     */
    public static function typed(PhpDocTokens $tokens): null
    {
        PhpDocTypeParser::type($tokens);
        PhpDocDescriptionParser::limited($tokens);
        return null;
    }

    public static function description(PhpDocTokens $tokens): null
    {
        PhpDocTextParser::text($tokens);
        return null;
    }

    /** @throws PhpDocSyntaxError */
    public static function template(PhpDocTokens $tokens): null
    {
        PhpDocGenericParser::template($tokens);
        PhpDocDescriptionParser::limited($tokens);
        return null;
    }

    /**
     * Parses a generic class type, such as for `@extends`.
     *
     * @throws PhpDocSyntaxError
     */
    public static function extends(PhpDocTokens $tokens): null
    {
        $name = $tokens->value();
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        PhpDocGenericParser::generic($tokens, $name);
        PhpDocDescriptionParser::limited($tokens);
        return null;
    }

    /**
     * Unlike other tags, an invalid alias type keeps the alias valid, and its description starts where parsing
     * stopped.
     *
     * @throws PhpDocSyntaxError
     */
    public static function typeAlias(PhpDocTokens $tokens): null
    {
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        $tokens->tryConsume(PhpDocLexer::EQUAL);
        try {
            PhpDocTypeParser::type($tokens);
            PhpDocTextParser::text($tokens);
        } catch (PhpDocSyntaxError) {
            PhpDocTextParser::text($tokens);
        }
        return null;
    }

    /**
     * Parses an alias import, which has no description, so text after it invalidates the whole doc comment.
     *
     * @throws PhpDocSyntaxError
     */
    public static function importType(PhpDocTokens $tokens): null
    {
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if (!$tokens->isValue('from')) {
            throw PhpDocSyntaxError::instance();
        }
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if ($tokens->isValue('as')) {
            $tokens->next();
            $tokens->consume(PhpDocLexer::IDENTIFIER);
        }
        return null;
    }

    /** @throws PhpDocSyntaxError */
    public static function assert(PhpDocTokens $tokens): null
    {
        $tokens->tryConsume(PhpDocLexer::NEGATED);
        $tokens->tryConsume(PhpDocLexer::EQUAL);
        PhpDocTypeParser::type($tokens);
        $object = $tokens->tryConsume(PhpDocLexer::THIS_VARIABLE);
        if (!$object) {
            $tokens->consume(PhpDocLexer::VARIABLE);
        }
        if ($tokens->is(PhpDocLexer::ARROW)) {
            $tokens->next();
            $tokens->consume(PhpDocLexer::IDENTIFIER);
            $method = $tokens->tryConsume(PhpDocLexer::OPEN_PARENTHESES);
            if ($method) {
                $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
            }
        }
        PhpDocTextParser::text($tokens);
        return null;
    }
}
