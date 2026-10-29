<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses a parameter in a `@method` tag value, such as `int ...$values = []`, with phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocMethodParameterTagValue
{
    /** @throws PhpDocSyntaxError */
    public static function parameter(PhpDocTokens $tokens): void
    {
        $typed =
            $tokens->is(PhpDocLexer::IDENTIFIER)
            || $tokens->is(PhpDocLexer::OPEN_PARENTHESES)
            || $tokens->is(PhpDocLexer::NULLABLE);
        if ($typed) {
            PhpDocTypeParser::type($tokens);
        }
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $tokens->consume(PhpDocLexer::VARIABLE);
        if ($tokens->is(PhpDocLexer::EQUAL)) {
            $tokens->next();
            PhpDocConstantParser::constant($tokens);
        }
    }
}
