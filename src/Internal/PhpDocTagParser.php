<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Reads the `@param` and `@var` tags of a doc comment, including their `@phan-`, `@psalm-`, and `@phpstan-` variants,
 * like phpstan/phpdoc-parser 2.x. All other tags and Doctrine-style annotations are parsed too, because where they end
 * decides where the next tag starts.
 *
 * @internal
 */
final class PhpDocTagParser
{
    private const int PARAM = 1;
    private const int TYPED_VARIABLE = 3;
    private const int VAR = 4;
    private const int TYPED = 5;
    private const int DESCRIPTION = 6;
    private const int METHOD = 7;
    private const int TEMPLATE = 8;
    private const int EXTENDS = 9;
    private const int TYPE_ALIAS = 10;
    private const int IMPORT_TYPE = 11;
    private const int ASSERT = 12;

    /** The grammar that phpdoc-parser uses for the value of each tag that it knows. */
    private const array KINDS = [
        '@param' => self::PARAM,
        '@phpstan-param' => self::PARAM,
        '@psalm-param' => self::PARAM,
        '@phan-param' => self::PARAM,
        '@param-immediately-invoked-callable' => self::DESCRIPTION,
        '@phpstan-param-immediately-invoked-callable' => self::DESCRIPTION,
        '@param-later-invoked-callable' => self::DESCRIPTION,
        '@phpstan-param-later-invoked-callable' => self::DESCRIPTION,
        '@param-closure-this' => self::TYPED_VARIABLE,
        '@phpstan-param-closure-this' => self::TYPED_VARIABLE,
        '@pure-unless-callable-is-impure' => self::DESCRIPTION,
        '@phpstan-pure-unless-callable-is-impure' => self::DESCRIPTION,
        '@pure-unless-parameter-passed' => self::DESCRIPTION,
        '@phpstan-pure-unless-parameter-passed' => self::DESCRIPTION,
        '@var' => self::VAR,
        '@phpstan-var' => self::VAR,
        '@psalm-var' => self::VAR,
        '@phan-var' => self::VAR,
        '@return' => self::TYPED,
        '@phpstan-return' => self::TYPED,
        '@psalm-return' => self::TYPED,
        '@phan-return' => self::TYPED,
        '@phan-real-return' => self::TYPED,
        '@throws' => self::TYPED,
        '@phpstan-throws' => self::TYPED,
        '@mixin' => self::TYPED,
        '@phan-mixin' => self::TYPED,
        '@psalm-require-extends' => self::TYPED,
        '@phpstan-require-extends' => self::TYPED,
        '@psalm-require-implements' => self::TYPED,
        '@phpstan-require-implements' => self::TYPED,
        '@psalm-inheritors' => self::TYPED,
        '@phpstan-sealed' => self::TYPED,
        '@deprecated' => self::DESCRIPTION,
        '@property' => self::TYPED_VARIABLE,
        '@property-read' => self::TYPED_VARIABLE,
        '@property-write' => self::TYPED_VARIABLE,
        '@phpstan-property' => self::TYPED_VARIABLE,
        '@phpstan-property-read' => self::TYPED_VARIABLE,
        '@phpstan-property-write' => self::TYPED_VARIABLE,
        '@psalm-property' => self::TYPED_VARIABLE,
        '@psalm-property-read' => self::TYPED_VARIABLE,
        '@psalm-property-write' => self::TYPED_VARIABLE,
        '@phan-property' => self::TYPED_VARIABLE,
        '@phan-property-read' => self::TYPED_VARIABLE,
        '@phan-property-write' => self::TYPED_VARIABLE,
        '@method' => self::METHOD,
        '@phpstan-method' => self::METHOD,
        '@psalm-method' => self::METHOD,
        '@phan-method' => self::METHOD,
        '@template' => self::TEMPLATE,
        '@phpstan-template' => self::TEMPLATE,
        '@psalm-template' => self::TEMPLATE,
        '@phan-template' => self::TEMPLATE,
        '@template-covariant' => self::TEMPLATE,
        '@phpstan-template-covariant' => self::TEMPLATE,
        '@psalm-template-covariant' => self::TEMPLATE,
        '@template-contravariant' => self::TEMPLATE,
        '@phpstan-template-contravariant' => self::TEMPLATE,
        '@psalm-template-contravariant' => self::TEMPLATE,
        '@extends' => self::EXTENDS,
        '@phpstan-extends' => self::EXTENDS,
        '@phan-extends' => self::EXTENDS,
        '@phan-inherits' => self::EXTENDS,
        '@template-extends' => self::EXTENDS,
        '@implements' => self::EXTENDS,
        '@phpstan-implements' => self::EXTENDS,
        '@template-implements' => self::EXTENDS,
        '@use' => self::EXTENDS,
        '@phpstan-use' => self::EXTENDS,
        '@template-use' => self::EXTENDS,
        '@phpstan-type' => self::TYPE_ALIAS,
        '@psalm-type' => self::TYPE_ALIAS,
        '@phan-type' => self::TYPE_ALIAS,
        '@phpstan-import-type' => self::IMPORT_TYPE,
        '@psalm-import-type' => self::IMPORT_TYPE,
        '@phpstan-assert' => self::ASSERT,
        '@phpstan-assert-if-true' => self::ASSERT,
        '@phpstan-assert-if-false' => self::ASSERT,
        '@psalm-assert' => self::ASSERT,
        '@psalm-assert-if-true' => self::ASSERT,
        '@psalm-assert-if-false' => self::ASSERT,
        '@phan-assert' => self::ASSERT,
        '@phan-assert-if-true' => self::ASSERT,
        '@phan-assert-if-false' => self::ASSERT,
        '@phpstan-this-out' => self::TYPED,
        '@phpstan-self-out' => self::TYPED,
        '@psalm-this-out' => self::TYPED,
        '@psalm-self-out' => self::TYPED,
        '@param-out' => self::TYPED_VARIABLE,
        '@phpstan-param-out' => self::TYPED_VARIABLE,
        '@psalm-param-out' => self::TYPED_VARIABLE,
    ];

    private const array LINE_ENDS = [PhpDocLexer::PHPDOC_EOL, PhpDocLexer::CLOSE_PHPDOC, PhpDocLexer::END];

    private const array GENERIC_DESCRIPTION_ENDS = [
        PhpDocLexer::PHPDOC_TAG,
        PhpDocLexer::DOCTRINE_TAG,
        PhpDocLexer::PHPDOC_EOL,
        PhpDocLexer::CLOSE_PHPDOC,
        PhpDocLexer::END,
    ];

    private readonly PhpDocTypeParser $types;

    private readonly PhpDocConstantParser $constants;

    private readonly PhpDocConstantParser $doctrineConstants;

    private function __construct(
        private readonly PhpDocTokens $tokens,
    ) {
        $this->types = new PhpDocTypeParser($tokens);
        $this->constants = new PhpDocConstantParser($tokens);
        $this->doctrineConstants = new PhpDocConstantParser($tokens, doctrine: true);
    }

    /**
     * Returns the `@param` and `@var` tags in order. A doc comment that phpdoc-parser cannot parse as a whole has no
     * tags.
     *
     * @return list<PhpDocTag>
     */
    public static function tags(string $doc): array
    {
        try {
            return new self(new PhpDocTokens($doc))->children();
        } catch (PhpDocSyntaxError) {
            return [];
        }
    }

    /**
     * @return list<PhpDocTag>
     * @throws PhpDocSyntaxError
     */
    private function children(): array
    {
        $tokens = $this->tokens;
        $tokens->consume(PhpDocLexer::OPEN_PHPDOC);
        $tags = [];
        $separated = true;
        while ($separated && !$tokens->is(PhpDocLexer::CLOSE_PHPDOC)) {
            $child = $this->child();
            if ($child instanceof PhpDocTag) {
                $tags[] = $child;
            }
            $lineEnd = $tokens->tryConsume(PhpDocLexer::PHPDOC_EOL);
            $separated = $lineEnd || $child === PhpDocChildKind::Generic;
        }
        // phpdoc-parser replaces all tags with one invalid tag if the comment does not end here.
        $complete = $tokens->is(PhpDocLexer::CLOSE_PHPDOC) && !$tokens->hasComments();
        return $complete ? $tags : [];
    }

    /**
     * Returns the tag if the child is a `@param` or `@var` tag that PHPStan reads, and the kind of child otherwise.
     *
     * @throws PhpDocSyntaxError
     */
    private function child(): PhpDocTag|PhpDocChildKind
    {
        $tokens = $this->tokens;
        $name = $tokens->value();
        if ($tokens->is(PhpDocLexer::PHPDOC_TAG)) {
            $tokens->next();
            return $this->tagValue($name);
        }
        if ($tokens->is(PhpDocLexer::DOCTRINE_TAG)) {
            $tokens->next();
            $this->doctrineAnnotation();
            return PhpDocChildKind::Generic;
        }
        $this->text();
        return PhpDocChildKind::Text;
    }

    /**
     * Parses a tag value after its name. Returns the tag if it is a `@param` or `@var` tag that PHPStan reads, and the
     * kind of tag otherwise. An invalid value is skipped as text.
     */
    private function tagValue(string $tag): PhpDocTag|PhpDocChildKind
    {
        $tokens = $this->tokens;
        try {
            $tokens->pushSavePoint();
            $value = $this->validTagValue($tag);
            $tokens->dropSavePoint();
            return $value;
        } catch (PhpDocSyntaxError) {
            $tokens->rollback();
            $this->text();
            return PhpDocChildKind::Invalid;
        }
    }

    /** @throws PhpDocSyntaxError */
    private function validTagValue(string $tag): PhpDocTag|PhpDocChildKind
    {
        $kind = self::KINDS[$tag] ?? null;
        if ($kind === null) {
            $this->doctrineAnnotation();
            return PhpDocChildKind::Generic;
        }
        if ($kind === self::PARAM) {
            return $this->paramValue($tag) ?? PhpDocChildKind::Valid;
        }
        if ($kind === self::VAR) {
            return $this->varValue($tag);
        }
        $this->otherValue($kind);
        return PhpDocChildKind::Valid;
    }

    /**
     * Parses the value of a tag other than `@param` and `@var`. Its value is not used.
     *
     * @throws PhpDocSyntaxError
     */
    private function otherValue(int $kind): void
    {
        switch ($kind) {
            case self::TYPED_VARIABLE:
                $this->typedVariableValue();
                break;
            case self::TYPED:
                $this->typedValue();
                break;
            case self::DESCRIPTION:
                $this->text();
                break;
            case self::METHOD:
                $this->methodValue();
                break;
            case self::TEMPLATE:
                $this->types->template();
                $this->limitedDescription();
                break;
            case self::EXTENDS:
                $this->extendsValue();
                break;
            case self::TYPE_ALIAS:
                $this->typeAliasValue();
                break;
            case self::IMPORT_TYPE:
                $this->importTypeValue();
                break;
            default:
                $this->assertValue();
        }
    }

    /**
     * Returns the tag, or null for a `@param` tag without a type, which PHPStan does not read.
     *
     * @throws PhpDocSyntaxError
     */
    private function paramValue(string $tag): PhpDocTag|null
    {
        $tokens = $this->tokens;
        $typed = !$tokens->is(PhpDocLexer::VARIABLE);
        $type = $typed ? PhpDocTypeTree::limit($this->types->type()) : null;
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $variable = $tokens->value();
        $tokens->consume(PhpDocLexer::VARIABLE);
        $this->text();
        return $typed ? new PhpDocTag($tag, $variable, $type) : null;
    }

    /**
     * Returns the tag with its variable name, which is empty if the tag has none.
     *
     * @throws PhpDocSyntaxError
     */
    private function varValue(string $tag): PhpDocTag
    {
        $tokens = $this->tokens;
        $type = PhpDocTypeTree::limit($this->types->type());
        $variable = match (true) {
            $tokens->is(PhpDocLexer::VARIABLE) => $tokens->value(),
            $tokens->is(PhpDocLexer::THIS_VARIABLE) => '$this',
            default => '',
        };
        if ($variable === '') {
            $this->limitedDescription();
        }
        $this->text();
        return new PhpDocTag($tag, $variable, $type);
    }

    /**
     * Parses a type with a variable, such as for `@property`.
     *
     * @throws PhpDocSyntaxError
     */
    private function typedVariableValue(): void
    {
        $this->types->type();
        $this->tokens->consume(PhpDocLexer::VARIABLE);
        $this->text();
    }

    /**
     * Parses a type without a variable, such as for `@return`.
     *
     * @throws PhpDocSyntaxError
     */
    private function typedValue(): void
    {
        $this->types->type();
        $this->limitedDescription();
    }

    /**
     * Parses a generic class type, such as for `@extends`.
     *
     * @throws PhpDocSyntaxError
     */
    private function extendsValue(): void
    {
        $name = $this->tokens->value();
        $this->tokens->consume(PhpDocLexer::IDENTIFIER);
        $this->types->generic($name);
        $this->limitedDescription();
    }

    /**
     * Unlike other tags, an invalid alias type keeps the alias valid, and its description starts where parsing
     * stopped.
     *
     * @throws PhpDocSyntaxError
     */
    private function typeAliasValue(): void
    {
        $this->tokens->consume(PhpDocLexer::IDENTIFIER);
        $this->tokens->tryConsume(PhpDocLexer::EQUAL);
        try {
            $this->types->type();
            $this->text();
        } catch (PhpDocSyntaxError) {
            $this->text();
        }
    }

    /**
     * Parses an alias import, which has no description, so text after it invalidates the whole doc comment.
     *
     * @throws PhpDocSyntaxError
     */
    private function importTypeValue(): void
    {
        $tokens = $this->tokens;
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
    }

    /** @throws PhpDocSyntaxError */
    private function assertValue(): void
    {
        $tokens = $this->tokens;
        $tokens->tryConsume(PhpDocLexer::NEGATED);
        $tokens->tryConsume(PhpDocLexer::EQUAL);
        $this->types->type();
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
        $this->text();
    }

    /** @throws PhpDocSyntaxError */
    private function methodValue(): void
    {
        $tokens = $this->tokens;
        $name = $this->methodTypeName();
        if ($name === 'static') {
            $name = $this->methodTypeName();
        }
        $named = $tokens->tryConsume(PhpDocLexer::IDENTIFIER);
        if (!$named && $name === null) {
            throw PhpDocSyntaxError::instance();
        }
        if ($tokens->is(PhpDocLexer::OPEN_ANGLE_BRACKET)) {
            do {
                $tokens->next();
                $this->types->template();
            } while ($tokens->is(PhpDocLexer::COMMA));
            $tokens->consume(PhpDocLexer::CLOSE_ANGLE_BRACKET);
        }
        $tokens->consume(PhpDocLexer::OPEN_PARENTHESES);
        $more = !$tokens->is(PhpDocLexer::CLOSE_PARENTHESES);
        while ($more) {
            $this->methodParameter();
            $more = $tokens->tryConsume(PhpDocLexer::COMMA);
        }
        $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        $this->text();
    }

    /**
     * Parses a type and returns its name if it is a plain name, which can then be the method name. Parentheses around
     * the name do not matter.
     *
     * @throws PhpDocSyntaxError
     */
    private function methodTypeName(): string|null
    {
        $type = $this->types->type();
        $name =
            $type !== null
            && $type->arguments === []
            && $type->entries === []
            && PhpDocLexer::isIdentifier($type->name);
        return $name ? $type->name : null;
    }

    /**
     * Parses a parameter such as `int ...$values = []`.
     *
     * @throws PhpDocSyntaxError
     */
    private function methodParameter(): void
    {
        $tokens = $this->tokens;
        $typed =
            $tokens->is(PhpDocLexer::IDENTIFIER)
            || $tokens->is(PhpDocLexer::OPEN_PARENTHESES)
            || $tokens->is(PhpDocLexer::NULLABLE);
        if ($typed) {
            $this->types->type();
        }
        $tokens->tryConsume(PhpDocLexer::REFERENCE);
        $tokens->tryConsume(PhpDocLexer::VARIADIC);
        $tokens->consume(PhpDocLexer::VARIABLE);
        if ($tokens->is(PhpDocLexer::EQUAL)) {
            $tokens->next();
            $this->constants->constant();
        }
    }

    /**
     * Skips the rest of the line. phpdoc-parser 2.x continues text on later lines until a line starts with a tag, but
     * those lines are text on their own as well, so skipping one line at a time reads the same tags.
     */
    private function text(): void
    {
        $this->tokens->skipUntil(self::LINE_ENDS);
    }

    /**
     * Skips the description after a type, which must be separated by whitespace and must not start with `|` or `&`, so
     * that the type cannot continue into it.
     *
     * @throws PhpDocSyntaxError
     */
    private function limitedDescription(): void
    {
        $tokens = $this->tokens;
        $continuesType = $tokens->is(PhpDocLexer::UNION) || $tokens->is(PhpDocLexer::INTERSECTION);
        $ends =
            $tokens->is(PhpDocLexer::PHPDOC_EOL)
            || $tokens->is(PhpDocLexer::CLOSE_PHPDOC)
            || $tokens->is(PhpDocLexer::END);
        if ($continuesType || !$ends && !$tokens->precededByWhitespace()) {
            throw PhpDocSyntaxError::instance();
        }
        $this->text();
    }

    /**
     * Skips the description of a tag that phpdoc-parser does not know. It ends at a later tag on the same line that is
     * unknown as well.
     */
    private function genericDescription(): void
    {
        $this->tokens->skipUntil(self::GENERIC_DESCRIPTION_ENDS);
        $ends = $this->endsAtInlineTag();
        if (!$ends) {
            $this->text();
        }
    }

    /**
     * Whether the description ends at the current tag, which is the case for a Doctrine annotation or a tag that
     * phpdoc-parser does not know. phpdoc-parser also ends it before an invalid tag followed by `(`, which then reads
     * the same as a description. At the end of a line, the result does not matter.
     */
    private function endsAtInlineTag(): bool
    {
        $tokens = $this->tokens;
        if (!$tokens->precededByWhitespace()) {
            return false;
        }
        if (!$tokens->is(PhpDocLexer::PHPDOC_TAG)) {
            return true;
        }
        $tokens->pushSavePoint();
        $name = $tokens->value();
        $tokens->next();
        $value = $this->tagValue($name);
        $tokens->rollback();
        return $value === PhpDocChildKind::Generic;
    }

    /**
     * Parses the arguments and the description of a Doctrine-style annotation such as `@ORM\Column(name="id")` after
     * its name. The arguments may span lines.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineAnnotation(): void
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->skipLineEndsAsWhitespace(true);
            try {
                $tokens->next();
                $this->doctrineArguments();
            } finally {
                $tokens->skipLineEndsAsWhitespace(false);
            }
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        }
        $this->genericDescription();
    }

    /**
     * Parses the arguments after `(` up to the closing parenthesis.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineArguments(): void
    {
        $more = true;
        while ($more && !$this->tokens->is(PhpDocLexer::CLOSE_PARENTHESES)) {
            $this->doctrineArgument();
            $more = $this->tokens->tryConsume(PhpDocLexer::COMMA);
        }
    }

    /**
     * Parses an argument, which may be named with `name=`.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineArgument(): void
    {
        $tokens = $this->tokens;
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            $this->doctrineValue();
            return;
        }
        try {
            $tokens->pushSavePoint();
            $tokens->next();
            $tokens->consume(PhpDocLexer::EQUAL);
            $this->doctrineValue();
            $tokens->dropSavePoint();
        } catch (PhpDocSyntaxError) {
            $tokens->rollback();
            $this->doctrineValue();
        }
    }

    /** @throws PhpDocSyntaxError */
    private function doctrineValue(): void
    {
        if ($this->tokens->is(PhpDocLexer::OPEN_CURLY_BRACKET)) {
            $this->tokens->next();
            $this->doctrineArray();
            return;
        }
        $annotation = $this->nestedDoctrineAnnotation();
        if (!$annotation) {
            $this->doctrineScalar();
        }
    }

    /**
     * Parses a nested annotation with its optional arguments, where line ends are skipped as in the outer annotation.
     * Returns false if no annotation starts here.
     *
     * @throws PhpDocSyntaxError
     */
    private function nestedDoctrineAnnotation(): bool
    {
        $tokens = $this->tokens;
        if (!$tokens->is(PhpDocLexer::PHPDOC_TAG) && !$tokens->is(PhpDocLexer::DOCTRINE_TAG)) {
            return false;
        }
        $tokens->next();
        if ($tokens->is(PhpDocLexer::OPEN_PARENTHESES)) {
            $tokens->next();
            $this->doctrineArguments();
            $tokens->consume(PhpDocLexer::CLOSE_PARENTHESES);
        }
        return true;
    }

    /**
     * Parses a name, or a constant expression other than an array.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineScalar(): void
    {
        $tokens = $this->tokens;
        $tokens->pushSavePoint();
        $name = $tokens->tryConsume(PhpDocLexer::IDENTIFIER) && !$tokens->is(PhpDocLexer::DOUBLE_COLON);
        if ($name) {
            $tokens->dropSavePoint();
            return;
        }
        $tokens->rollback();
        $constant = $this->doctrineConstants->constant();
        if ($constant === null) {
            throw PhpDocSyntaxError::instance();
        }
    }

    /**
     * Parses the items of an array such as `{"a": 1, Foo::BAR = 2}` after `{` up to the closing brace.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineArray(): void
    {
        $more = true;
        while ($more && !$this->tokens->is(PhpDocLexer::CLOSE_CURLY_BRACKET)) {
            $this->doctrineArrayItem();
            $more = $this->tokens->tryConsume(PhpDocLexer::COMMA);
        }
        $this->tokens->consume(PhpDocLexer::CLOSE_CURLY_BRACKET);
    }

    /**
     * Parses an item, which may have a key followed by `=` or `:`.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineArrayItem(): void
    {
        $tokens = $this->tokens;
        try {
            $tokens->pushSavePoint();
            $this->doctrineArrayKey();
            $separated = $tokens->tryConsume(PhpDocLexer::EQUAL);
            if (!$separated) {
                $separated = $tokens->tryConsume(PhpDocLexer::COLON);
            }
            if (!$separated) {
                throw PhpDocSyntaxError::instance();
            }
            $this->doctrineValue();
            $tokens->dropSavePoint();
        } catch (PhpDocSyntaxError) {
            $tokens->rollback();
            $this->doctrineValue();
        }
    }

    /** @throws PhpDocSyntaxError */
    private function doctrineArrayKey(): void
    {
        $tokens = $this->tokens;
        if ($tokens->is(PhpDocLexer::INTEGER) || $tokens->is(PhpDocLexer::SINGLE_QUOTED_STRING)) {
            $tokens->next();
            return;
        }
        if ($tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING) || $tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING)) {
            $this->doctrineArrayKeyStrings();
            return;
        }
        $this->doctrineArrayKeyName();
    }

    /**
     * Parses a key that is a name or a class constant.
     *
     * @throws PhpDocSyntaxError
     */
    private function doctrineArrayKeyName(): void
    {
        $tokens = $this->tokens;
        if (!$tokens->is(PhpDocLexer::IDENTIFIER)) {
            throw PhpDocSyntaxError::instance();
        }
        $tokens->pushSavePoint();
        $tokens->next();
        if (!$tokens->is(PhpDocLexer::DOUBLE_COLON)) {
            $tokens->dropSavePoint();
            return;
        }
        $tokens->rollback();
        $this->doctrineConstants->constant();
    }

    /** Skips a key of adjacent strings, which may be the parts of a string with doubled quotes. */
    private function doctrineArrayKeyStrings(): void
    {
        $tokens = $this->tokens;
        // phpdoc-parser skips one more token after a key that starts with a multi-line string.
        $extra = $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING);
        while ($tokens->is(PhpDocLexer::DOUBLE_QUOTED_STRING) || $tokens->is(PhpDocLexer::DOCTRINE_ANNOTATION_STRING)) {
            $tokens->next();
        }
        if ($extra) {
            $tokens->next();
        }
    }
}
