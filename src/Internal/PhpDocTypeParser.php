<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;
use function str_contains;
use function str_replace;
use function strlen;
use function substr_compare;

/**
 * Parses PHPDoc types with phpstan/phpdoc-parser 2.x's grammar. The parser follows phpdoc-parser step by step,
 * because where it backtracks decides which declarations are valid.
 *
 * Methods return the type, or null for syntax that the decoder does not support, such as `?T`, `T[]`, callables,
 * conditional types, object shapes, and open array shapes. The decoder does not support callables either, but parsing
 * them decides where a declaration ends.
 *
 * @internal
 */
final class PhpDocTypeParser
{
    private const array SHAPE_KINDS = ['array', 'list', 'non-empty-array', 'non-empty-list', 'object'];

    private readonly PhpDocConstantParser $constants;

    public function __construct(
        private readonly PhpDocTokens $tokens,
    ) {
        $this->constants = new PhpDocConstantParser($tokens);
    }

    /** Parses a standalone type such as `list<int>`. */
    public static function parse(string $source): PhpDocType|null
    {
        $tokens = new PhpDocTokens($source);
        try {
            $type = new self($tokens)->type();
        } catch (PhpDocSyntaxError) {
            return null;
        }
        $complete = $tokens->is(PhpDocLexer::END);
        return $complete ? PhpDocTypeTree::limit($type) : null;
    }

    /**
     * Parses a type, where a union or intersection may continue on the next line.
     *
     * @throws PhpDocSyntaxError
     */
    public function type(): PhpDocType|null
    {
        if ($this->tokens->is(PhpDocLexer::NULLABLE)) {
            return $this->nullable();
        }
        $type = $this->atomic();
        $compound = $this->compoundAcrossLines($type);
        return $compound === false ? $type : $compound;
    }

    /**
     * Parses the generic arguments of the given type name, starting at `<`. The type is null if an argument has a
     * variance or is the `*` wildcard.
     *
     * @throws PhpDocSyntaxError
     */
    public function generic(string $name): PhpDocType|null
    {
        $tokens = $this->tokens;
        $tokens->consume(PhpDocLexer::OPEN_ANGLE_BRACKET);
        $tokens->skipLineEnds();
        $arguments = [];
        $more = true;
        while ($more) {
            $arguments[] = $this->genericArgument();
            $more = $this->listContinues(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        }
        $tokens->flushComments();
        $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        return PhpDocTypeTree::named($name, $arguments);
    }

    /**
     * Parses a template such as `T of Foo = Bar`, without a description.
     *
     * @throws PhpDocSyntaxError
     */
    public function template(): void
    {
        $this->tokens->consume(PhpDocLexer::IDENTIFIER);
        foreach ([['of', 'as'], ['super'], ['=']] as $keywords) {
            $bound = $this->keyword($keywords);
            if ($bound) {
                $this->type();
            }
        }
    }

    /**
     * Tries a union or intersection that continues on the next line first, then one on the same line. Returns false
     * if none follows.
     *
     * @throws PhpDocSyntaxError
     */
    private function compoundAcrossLines(PhpDocType|null $type): PhpDocType|false|null
    {
        $tokens = $this->tokens;
        $tokens->pushSavePoint();
        $tokens->skipLineEnds();
        try {
            $compound = $this->compound($type);
        } catch (PhpDocSyntaxError) {
            $compound = false;
        }
        if ($compound !== false) {
            $tokens->dropSavePoint();
            return $compound;
        }
        $tokens->rollback();
        return $this->compound($type);
    }

    /**
     * Returns false if no union or intersection follows.
     *
     * @throws PhpDocSyntaxError
     */
    private function compound(PhpDocType|null $type): PhpDocType|false|null
    {
        $tokens = $this->tokens;
        $operator = $tokens->is(PhpDocLexer::UNION) ? PhpDocLexer::UNION : PhpDocLexer::INTERSECTION;
        if (!$tokens->is($operator)) {
            return false;
        }
        $types = [$type];
        $continues = true;
        while ($continues) {
            $tokens->next();
            $types[] = $this->atomic();
            $tokens->pushSavePoint();
            $tokens->skipLineEnds();
            $continues = $tokens->is($operator);
            if ($continues) {
                $tokens->dropSavePoint();
            }
        }
        $tokens->rollback();
        return PhpDocTypeTree::named($operator === PhpDocLexer::UNION ? '|' : '&', $types);
    }

    /**
     * Parses a type inside parentheses, where unions and intersections may span lines and conditional types are
     * allowed.
     *
     * @throws PhpDocSyntaxError
     */
    private function nested(): PhpDocType|null
    {
        $type = $this->unflushedNested();
        $this->tokens->flushComments();
        return $type;
    }

    /** @throws PhpDocSyntaxError */
    private function unflushedNested(): PhpDocType|null
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::NULLABLE)) {
            return $this->nullable();
        }
        if ($tokens->is(PhpDocLexer::VARIABLE)) {
            $tokens->next();
            if (!$tokens->isValue('is')) {
                throw PhpDocSyntaxError::instance();
            }
            return $this->conditional();
        }
        $type = $this->atomic();
        if ($tokens->isValue('is')) {
            return $this->conditional();
        }
        $tokens->skipLineEnds();
        return $this->nestedCompound($type);
    }

    /**
     * Parses a conditional type from `is` on, such as `is int ? A : B`.
     *
     * @throws PhpDocSyntaxError
     */
    private function conditional(): null
    {
        $tokens = $this->tokens;
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if ($tokens->isValue('not')) {
            $tokens->consume(PhpDocLexer::IDENTIFIER);
        }
        $this->type();
        $tokens->skipLineEnds();
        $tokens->consume(PhpDocLexer::NULLABLE);
        $tokens->skipLineEnds();
        $this->type();
        $tokens->skipLineEnds();
        $tokens->consume(PhpDocLexer::COLON);
        $tokens->skipLineEnds();
        $this->nested();
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private function nestedCompound(PhpDocType|null $type): PhpDocType|null
    {
        $tokens = $this->tokens;
        $operator = $tokens->is(PhpDocLexer::UNION) ? PhpDocLexer::UNION : PhpDocLexer::INTERSECTION;
        if (!$tokens->is($operator)) {
            return $type;
        }
        $types = [$type];
        while ($tokens->is($operator)) {
            $tokens->next();
            $tokens->skipLineEnds();
            $types[] = $this->atomic();
            $tokens->skipLineEnds();
        }
        return PhpDocTypeTree::named($operator === PhpDocLexer::UNION ? '|' : '&', $types);
    }

    /**
     * Parses a type without a top-level union or intersection.
     *
     * @throws PhpDocSyntaxError
     */
    private function atomic(): PhpDocType|null
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            return $this->parenthesized();
        }
        if ($tokens->is(PhpDocLexer::THIS_VARIABLE)) {
            $tokens->next();
            $this->arrayOrOffsetAccess(null);
            $tokens->flushComments();
            return null;
        }
        $name = $this->typeName();
        if ($name === null) {
            return $this->constant();
        }
        $tokens->flushComments();
        $tokens->dropSavePoint();
        return $this->named($name);
    }

    /** @throws PhpDocSyntaxError */
    private function nullable(): null
    {
        $this->tokens->consume(PhpDocLexer::NULLABLE);
        $this->atomic();
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private function parenthesized(): PhpDocType|null
    {
        $tokens = $this->tokens;
        $tokens->skipLineEnds();
        $type = $this->nested();
        $tokens->skipLineEnds();
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        $type = $this->arrayOrOffsetAccess($type);
        $tokens->flushComments();
        return $type;
    }

    /**
     * Consumes a type name and returns it, leaving a save point before it on the stack. Returns null, with the
     * position unchanged, if a constant such as `'foo'` or `Foo::BAR` follows instead.
     */
    private function typeName(): string|null
    {
        $tokens = $this->tokens;
        $name = $tokens->value();
        $tokens->pushSavePoint();
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            $tokens->dropSavePoint();
            return null;
        }
        $tokens->next();
        if ($tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            $tokens->rollback();
            return null;
        }
        return $name;
    }

    /** @throws PhpDocSyntaxError */
    private function constant(): PhpDocType|null
    {
        $name = $this->constantName();
        $this->tokens->flushComments();
        return $this->arrayOrOffsetAccess(new PhpDocType($name));
    }

    /**
     * Parses a constant, which must not be an array.
     *
     * @throws PhpDocSyntaxError
     */
    private function constantName(): string
    {
        $name = $this->constants->constant();
        if ($name === null) {
            throw PhpDocSyntaxError::instance();
        }
        return $name;
    }

    /**
     * Parses what follows a type name, such as generic arguments, a shape, or callable parameters.
     *
     * @throws PhpDocSyntaxError
     */
    private function named(string $name): PhpDocType|null
    {
        $tokens = $this->tokens;
        $html = $tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET) && $tokens->mayHaveHtmlEndTag() && $this->isHtml();
        if ($html) {
            return new PhpDocType($name);
        }
        $type = $this->continued($name);
        $tokens->flushComments();
        return $type;
    }

    /** @throws PhpDocSyntaxError */
    private function continued(string $name): PhpDocType|null
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $callable = $this->tryCallable();
            return $callable ? null : new PhpDocType($name);
        }
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $callable = $this->tryCallable();
            return $callable ? null : $this->arrayOrOffsetAccess($this->generic($name));
        }
        $type = $this->startsShape($name) ? $this->shape($name) : new PhpDocType($name);
        return $this->arrayOrOffsetAccess($type);
    }

    /**
     * Returns the type unchanged if no `[]` or `[K]` follows it, or null for an array or offset access. An invalid
     * access is not parsed.
     */
    private function arrayOrOffsetAccess(PhpDocType|null $type): PhpDocType|null
    {
        try {
            while ($this->tokens->is(PhpDocLexer::OPEN_SQUARE_BRACKET)) {
                $this->access();
                $type = null;
            }
        } catch (PhpDocSyntaxError) {
            $this->tokens->rollback();
        }
        return $type;
    }

    /**
     * Parses `[]` or `[K]`. The save point stays on the stack if parsing fails.
     *
     * @throws PhpDocSyntaxError
     */
    private function access(): void
    {
        $tokens = $this->tokens;
        $tokens->pushSavePoint();
        $offsetAccess = !$tokens->precededByWhitespace();
        $tokens->next();
        if ($offsetAccess && !$tokens->is(PhpDocLexer::CLOSE_SQUARE_BRACKET)) {
            $this->type();
        }
        $tokens->consume(PhpDocLexer::CLOSE_SQUARE_BRACKET);
        $tokens->dropSavePoint();
    }

    /**
     * Whether an HTML element such as `<p>…</p>` starts at the current `<` and its end tag follows later, which
     * phpdoc-parser does not read as generic arguments. Keeps the position.
     */
    private function isHtml(): bool
    {
        $tokens = $this->tokens;
        $tokens->pushSavePoint();
        $tokens->next();
        $html = $this->htmlElement();
        $tokens->rollback();
        return $html;
    }

    private function htmlElement(): bool
    {
        $tokens = $this->tokens;
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            return false;
        }
        $tag = $tokens->value();
        $tokens->next();
        if (!$tokens->is(PhpDocLexer::CLOSE_ANGLE_BRACKET)) {
            return false;
        }
        while (!$tokens->is(PhpDocLexer::END)) {
            $closes = $this->closesHtml($tag);
            if ($closes) {
                return true;
            }
            $tokens->next();
        }
        return false;
    }

    private function closesHtml(string $tag): bool
    {
        $tokens = $this->tokens;
        $end = '</' . $tag . '>';
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $tokens->next();
            if (str_contains($tokens->value(), '/' . $tag . '>')) {
                return true;
            }
        }
        return substr_compare($tokens->value(), $end, -strlen($end)) === 0;
    }

    /** @throws PhpDocSyntaxError */
    private function genericArgument(): PhpDocType|null
    {
        if ($this->tokens->is(PhpDocLexer::WILDCARD)) {
            $this->tokens->next();
            return null;
        }
        $variance = $this->keyword(['contravariant', 'covariant']);
        $type = $this->type();
        return $variance ? null : $type;
    }

    /**
     * Consumes the first of the given keywords that follows.
     *
     * @param non-empty-list<string> $keywords
     */
    private function keyword(array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (!$this->tokens->isValue($keyword)) {
                continue;
            }
            $this->tokens->next();
            return true;
        }
        return false;
    }

    /**
     * Ends a list item and consumes the comma after it. Returns whether another item follows, which it does not after
     * a trailing comma.
     */
    private function listContinues(int $close): bool
    {
        $tokens = $this->tokens;
        $tokens->skipLineEnds();
        $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        $tokens->skipLineEnds();
        return $more && !$tokens->is($close);
    }

    /** Parses a callable such as `(int): string` after its name if possible, and otherwise restores the position. */
    private function tryCallable(): bool
    {
        $tokens = $this->tokens;
        if (!$tokens->parenthesisFollows()) {
            return false;
        }
        try {
            $tokens->pushSavePoint();
            $this->callable();
            $tokens->dropSavePoint();
            return true;
        } catch (PhpDocSyntaxError) {
            $tokens->rollback();
            return false;
        }
    }

    /** @throws PhpDocSyntaxError */
    private function callable(): void
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $this->callableTemplates();
        }
        $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
        $tokens->skipLineEnds();
        $more = !$tokens->is(PhpDocLexer::CLOSE_PARENTHESES);
        while ($more) {
            $this->callableParameter();
            $more = $this->listContinues(PhpDocLexer::CLOSE_PARENTHESES);
        }
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        $tokens->consume(PhpDocLexer::COLON);
        $this->callableReturnType();
    }

    /** @throws PhpDocSyntaxError */
    private function callableTemplates(): void
    {
        $tokens = $this->tokens;
        $tokens->next();
        $more = true;
        while ($more) {
            $tokens->skipLineEnds();
            $this->template();
            $more = $this->listContinues(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        }
        $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
    }

    /** @throws PhpDocSyntaxError */
    private function callableParameter(): void
    {
        $tokens = $this->tokens;
        $this->type();
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $tokens->tryConsume(PhpDocLexer::VARIABLE);
        $tokens->tryConsume(PhpDocLexer::EQUAL);
    }

    /**
     * Parses the return type of a callable, which differs from other types: unions must be parenthesized.
     *
     * @throws PhpDocSyntaxError
     */
    private function callableReturnType(): void
    {
        if ($this->tokens->is(PhpDocLexer::NULLABLE)) {
            $this->nullable();
            return;
        }
        $this->callableReturnOperand();
        $this->arrayOrOffsetAccess(null);
    }

    /**
     * Parses what an array or offset access in a callable return type may follow.
     *
     * @throws PhpDocSyntaxError
     */
    private function callableReturnOperand(): void
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            $this->nested();
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
            return;
        }
        if ($tokens->is(PhpDocLexer::THIS_VARIABLE)) {
            $tokens->next();
            return;
        }
        $this->callableReturnName();
    }

    /**
     * Parses a type name with what may follow it in a callable return type, or a constant.
     *
     * @throws PhpDocSyntaxError
     */
    private function callableReturnName(): void
    {
        $name = $this->typeName();
        if ($name === null) {
            $this->constantName();
            return;
        }
        // phpdoc-parser leaves the save point of the name on the stack, which shifts later rollbacks.
        if ($this->tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $this->generic($name);
            return;
        }
        if ($this->startsShape($name)) {
            $this->shape($name);
        }
    }

    /**
     * Whether a shape starts, which requires `{` directly after one of the shape names.
     *
     * @mutation-free
     */
    private function startsShape(string $name): bool
    {
        return (
            in_array($name, self::SHAPE_KINDS, strict: true)
            && $this->tokens->is(PhpDocLexer::OPEN_CURLY_BRACKET)
            && !$this->tokens->precededByWhitespace()
        );
    }

    /** @throws PhpDocSyntaxError */
    private function shape(string $kind): PhpDocType|null
    {
        return $kind === 'object' ? $this->objectShape() : $this->arrayShape($kind);
    }

    /**
     * Parses an array shape such as `array{0: int, 1?: string}`. Returns null for an open shape or an entry the decoder
     * does not support.
     *
     * @throws PhpDocSyntaxError
     */
    private function arrayShape(string $kind): PhpDocType|null
    {
        $tokens = $this->tokens;
        $tokens->consume(PhpDocLexer::OPEN_CURLY_BRACKET);
        $entries = [];
        $more = true;
        while ($more) {
            $tokens->skipLineEnds();
            if ($tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
                $tokens->next();
                return PhpDocTypeTree::shape($kind, $entries);
            }
            if ($tokens->is(PhpDocLexer::VARIADIC)) {
                $tokens->next();
                $this->unsealedShape($kind);
                return $this->closeArrayShape(null);
            }
            $entries[] = $this->arrayShapeItem();
            $tokens->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
            if ($tokens->is(PhpDocLexer::COMMENT)) {
                $tokens->next();
            }
        }
        return $this->closeArrayShape(PhpDocTypeTree::shape($kind, $entries));
    }

    /** @throws PhpDocSyntaxError */
    private function closeArrayShape(PhpDocType|null $shape): PhpDocType|null
    {
        $this->tokens->skipLineEnds();
        $this->tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
        return $shape;
    }

    /**
     * Parses the rest of an open shape after `...`, such as `<int, string>` for `array` or `<string>` for lists.
     *
     * @throws PhpDocSyntaxError
     */
    private function unsealedShape(string $kind): void
    {
        $tokens = $this->tokens;
        $tokens->skipLineEnds();
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            $tokens->next();
            $tokens->skipLineEnds();
            $this->type();
            $tokens->skipLineEnds();
            $keyed = $kind === 'array' && $tokens->tryConsume(PhpDocLexer::COMMA);
            if ($keyed) {
                $tokens->skipLineEnds();
                $this->type();
                $tokens->skipLineEnds();
            }
            $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
            $tokens->skipLineEnds();
        }
        $tokens->tryConsume(PhpDocLexer::COMMA);
    }

    /**
     * Returns the entry, or null if the decoder does not support its type. An entry without a valid key is parsed
     * again as a type.
     *
     * @throws PhpDocSyntaxError
     */
    private function arrayShapeItem(): PhpDocTupleEntry|null
    {
        $tokens = $this->tokens;
        try {
            $tokens->pushSavePoint();
            $key = $this->arrayShapeKey();
            $optional = $tokens->tryConsume(PhpDocLexer::NULLABLE);
            $tokens->consume(PhpDocLexer::COLON);
            $type = $this->type();
            $tokens->dropSavePoint();
        } catch (PhpDocSyntaxError) {
            $tokens->rollback();
            $key = null;
            $optional = false;
            $type = $this->type();
        }
        return $type === null ? null : new PhpDocTupleEntry($type, $key, $optional);
    }

    /** @throws PhpDocSyntaxError */
    private function arrayShapeKey(): string
    {
        $tokens = $this->tokens;
        $value = $tokens->value();
        if ($tokens->is(PhpDocLexer::INTEGER)) {
            $tokens->next();
            return str_replace('_', replace: '', subject: $value);
        }
        $quote = $this->constants->quote();
        if ($quote !== null) {
            $tokens->next();
            return PhpDocLiteral::quote(PhpDocLiteralString::unescape($value), $quote);
        }
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            return $value;
        }
        $tokens->next();
        $member = $tokens->value();
        $tokens->consume(PhpDocLexer::IDENTIFIER);
        return $value . '::' . $member;
    }

    /**
     * Parses an object shape, which the decoder does not support.
     *
     * @throws PhpDocSyntaxError
     */
    private function objectShape(): null
    {
        $tokens = $this->tokens;
        $tokens->consume(PhpDocLexer::OPEN_CURLY_BRACKET);
        $more = true;
        while ($more) {
            $tokens->skipLineEnds();
            if ($tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
                $tokens->next();
                return null;
            }
            $this->objectShapeItem();
            $tokens->skipLineEnds();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
        $tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
        return null;
    }

    /** @throws PhpDocSyntaxError */
    private function objectShapeItem(): void
    {
        $tokens = $this->tokens;
        $quoted = $this->constants->quote() !== null;
        if ($quoted) {
            $tokens->next();
        }
        if (!$quoted) {
            $tokens->consume(PhpDocLexer::IDENTIFIER);
        }
        $tokens->flushComments();
        $tokens->tryConsume(PhpDocLexer::NULLABLE);
        $tokens->consume(PhpDocLexer::COLON);
        $this->type();
    }
}
