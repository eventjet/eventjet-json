<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses `@method` tag values with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocMethodTagValue
{
    /** @throws PhpDocSyntaxError */
    public static function method(PhpDocTokens $tokens): null
    {
        $name = self::typeName($tokens);
        if ($name === 'static') {
            $name = self::typeName($tokens);
        }
        $named = $tokens->tryConsume(PhpDocLexer::IDENTIFIER);
        if (!$named && $name === null) {
            throw PhpDocSyntaxError::instance();
        }
        self::templates($tokens);
        $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
        self::parameters($tokens);
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        PhpDocTextParser::text($tokens);
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private static function templates(PhpDocTokens $tokens): void
    {
        if (!$tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            return;
        }
        $more = true;
        while ($more) {
            $tokens->next();
            PhpDocGenericParser::template($tokens);
            $more = $tokens->is(PhpDocLexer::COMMA);
        }
        $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
    }

    /** @throws PhpDocSyntaxError */
    private static function parameters(PhpDocTokens $tokens): void
    {
        $more = !$tokens->is(PhpDocLexer::CLOSE_PARENTHESES);
        while ($more) {
            PhpDocMethodParameterTagValue::parameter($tokens);
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
    }

    /**
     * Parses a type and returns its name if it is a plain name, which can then be the method name. Parentheses around
     * the name do not matter.
     *
     * @throws PhpDocSyntaxError
     */
    private static function typeName(PhpDocTokens $tokens): string|null
    {
        $type = PhpDocTypeParser::type($tokens);
        $name =
            $type !== null
            && $type->arguments === []
            && $type->entries === []
            && PhpDocLexer::isIdentifier($type->name);
        return $name ? $type->name : null;
    }
}
