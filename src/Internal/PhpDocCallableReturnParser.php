<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses the return type of a callable type with phpstan/phpdoc-parser 2.x's grammar, which differs from other types:
 * unions must be parenthesized.
 *
 * @internal
 */
final class PhpDocCallableReturnParser
{
    /** @throws PhpDocSyntaxError */
    public static function returnType(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::NULLABLE)) {
            PhpDocAtomicTypeParser::nullable($tokens);
            return;
        }
        self::operand($tokens);
        PhpDocAtomicTypeParser::arrayOrOffsetAccess($tokens, null);
    }

    /**
     * Parses what an array or offset access may follow.
     *
     * @throws PhpDocSyntaxError
     */
    private static function operand(PhpDocTokens $tokens): void
    {
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            PhpDocNestedTypeParser::nested($tokens);
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
            return;
        }
        if ($tokens->is(PhpDocLexer::THIS_VARIABLE)) {
            $tokens->next();
            return;
        }
        self::nameOrConstant($tokens);
    }

    /** @throws PhpDocSyntaxError */
    private static function nameOrConstant(PhpDocTokens $tokens): void
    {
        $name = $tokens->value();
        $state = $tokens->state;
        $state->push();
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            $state->drop();
            self::constant($tokens);
            return;
        }
        $tokens->next();
        if ($tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            $state->rollback();
            self::constant($tokens);
            return;
        }
        // phpdoc-parser leaves this save point on the stack, which shifts later rollbacks.
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            PhpDocGenericParser::generic($tokens, $name);
            return;
        }
        $shape = PhpDocNamedTypeParser::startsShape($tokens, $name);
        if (!$shape) {
            return;
        }
        if ($name === 'object') {
            PhpDocShapeItemParser::objectShape($tokens);
            return;
        }
        PhpDocArrayShapeParser::shape($tokens, $name);
    }

    /**
     * Parses a constant, which must not be an array.
     *
     * @throws PhpDocSyntaxError
     */
    private static function constant(PhpDocTokens $tokens): void
    {
        $name = PhpDocConstantParser::constant($tokens);
        if ($name === null) {
            throw PhpDocSyntaxError::instance();
        }
    }
}
