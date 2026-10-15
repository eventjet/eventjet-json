<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function in_array;

/**
 * Walks the tokens of a doc comment like phpstan/phpdoc-parser's token iterator. Horizontal whitespace is skipped.
 *
 * It also holds the save points and whether comments were consumed inside types. Parsers that throw between pushing
 * and dropping a save point leave it on the stack, as phpdoc-parser does, so a later rollback can restore an earlier
 * position than the one it saved.
 *
 * @internal
 */
final class PhpDocTokens
{
    private int $index = -1;

    /** Whether line ends are skipped like whitespace, as inside the arguments of a Doctrine annotation. */
    private bool $lineEndsAreWhitespace = false;

    /** Whether comments were consumed inside types and not yet attached to a type, which phpdoc-parser forbids. */
    private bool $comments = false;

    /** @var list<array{int, bool}> */
    private array $savePoints = [];

    /** @var non-empty-list<string> */
    private readonly array $values;

    /** @var non-empty-list<int> */
    private readonly array $types;

    public function __construct(string $source)
    {
        [$this->values, $this->types] = PhpDocLexer::tokenize($source);
        $this->next();
    }

    /**
     * Past the last token, which only a standalone type can reach, the position counts as the end. phpdoc-parser fails
     * there instead.
     *
     * @mutation-free
     */
    public function is(int $type): bool
    {
        return ($this->types[$this->index] ?? PhpDocLexer::END) === $type;
    }

    /** @mutation-free */
    public function isValue(string $value): bool
    {
        return ($this->values[$this->index] ?? '') === $value;
    }

    /** @mutation-free */
    public function value(): string
    {
        return $this->values[$this->index] ?? '';
    }

    /** @mutation-free */
    public function precededByWhitespace(): bool
    {
        return ($this->types[$this->index - 1] ?? null) === PhpDocLexer::HORIZONTAL_WS;
    }

    public function next(): void
    {
        do {
            $this->index++;
            $type = $this->types[$this->index] ?? PhpDocLexer::END;
            $skipped =
                $type === PhpDocLexer::HORIZONTAL_WS
                || $this->lineEndsAreWhitespace && $type === PhpDocLexer::PHPDOC_EOL;
        } while ($skipped);
    }

    public function tryConsume(int $type): bool
    {
        if (!$this->is($type)) {
            return false;
        }
        $this->next();
        return true;
    }

    /** @throws PhpDocSyntaxError */
    public function consume(int $type): void
    {
        if (!$this->is($type)) {
            throw PhpDocSyntaxError::instance();
        }
        $this->next();
    }

    /**
     * Moves to the next token of one of the given types, without skipping whitespace on the way.
     *
     * @param list<int> $types
     */
    public function skipUntil(array $types): void
    {
        while (!in_array($this->types[$this->index] ?? PhpDocLexer::END, $types, strict: true)) {
            $this->index++;
        }
    }

    public function skipLineEndsAsWhitespace(bool $skip): void
    {
        $this->lineEndsAreWhitespace = $skip;
    }

    /** Skips line ends inside a type, together with any comments before or between them. */
    public function skipLineEnds(): void
    {
        $this->skipComment();
        $found = $this->is(PhpDocLexer::PHPDOC_EOL);
        while ($found) {
            $found = $this->tryConsume(PhpDocLexer::PHPDOC_EOL);
            $this->skipComment();
        }
    }

    public function flushComments(): void
    {
        $this->comments = false;
    }

    /** @mutation-free */
    public function hasComments(): bool
    {
        return $this->comments;
    }

    public function pushSavePoint(): void
    {
        $this->savePoints[] = [$this->index, $this->comments];
    }

    public function dropSavePoint(): void
    {
        array_pop($this->savePoints);
    }

    public function rollback(): void
    {
        $savePoint = array_pop($this->savePoints);
        if ($savePoint !== null) {
            [$this->index, $this->comments] = $savePoint;
        }
    }

    private function skipComment(): void
    {
        if ($this->is(PhpDocLexer::COMMENT)) {
            $this->next();
            $this->comments = true;
        }
    }
}
