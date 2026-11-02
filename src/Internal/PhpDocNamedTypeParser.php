<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;

/**
 * Parses what follows a type name, such as generic arguments, a shape, or callable parameters, with
 * phpstan/phpdoc-parser 2.x's grammar.
 *
 * @internal
 */
final class PhpDocNamedTypeParser
{
    private const array SHAPE_KINDS = ['array', 'list', 'non-empty-array', 'non-empty-list', 'object'];

    /** @throws PhpDocSyntaxError */
    public static function named(PhpDocTokens $tokens, string $name): PhpDocType|null
    {
        $html = $tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET) && $tokens->slash && PhpDocHtmlDetector::follows($tokens);
        if ($html) {
            return new PhpDocType($name);
        }
        $type = self::continued($tokens, $name);
        $tokens->state->flushComments();
        return $type;
    }

    /** Whether a shape starts, which requires `{` directly after one of the shape names. */
    public static function startsShape(PhpDocTokens $tokens, string $name): bool
    {
        return (
            in_array($name, self::SHAPE_KINDS, strict: true)
            && $tokens->is(PhpDocLexer::OPEN_CURLY_BRACKET)
            && !$tokens->precededByWhitespace()
        );
    }

    /** @throws PhpDocSyntaxError */
    private static function continued(PhpDocTokens $tokens, string $name): PhpDocType|null
    {
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $callable = self::callable($tokens);
            return $callable ? null : new PhpDocType($name);
        }
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $callable = self::callable($tokens);
            $type = $callable ? null : PhpDocGenericParser::generic($tokens, $name);
            return $callable ? null : PhpDocAtomicTypeParser::arrayOrOffsetAccess($tokens, $type);
        }
        $shape = self::startsShape($tokens, $name);
        if (!$shape) {
            return PhpDocAtomicTypeParser::arrayOrOffsetAccess($tokens, new PhpDocType($name));
        }
        $type = $name === 'object'
            ? PhpDocShapeItemParser::objectShape($tokens)
            : PhpDocArrayShapeParser::shape($tokens, $name);
        return PhpDocAtomicTypeParser::arrayOrOffsetAccess($tokens, $type);
    }

    /** Parses a callable if possible. A callable needs a `(`, so none can start after the last one. */
    private static function callable(PhpDocTokens $tokens): bool
    {
        $last = $tokens->lastParenthesis;
        return $last !== null && $tokens->index <= $last && PhpDocCallableParser::attempt($tokens);
    }
}
