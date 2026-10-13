<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Walks the tokens of a doc comment like phpstan/phpdoc-parser's token iterator. Horizontal whitespace is skipped.
 *
 * @internal
 */
final class PhpDocTokens
{
    public int $index = -1;

    /** Whether line ends are skipped like whitespace, as inside the arguments of a Doctrine annotation. */
    public bool $lineEndsAreWhitespace = false;

    public readonly PhpDocTokenState $state;

    /**
     * @param non-empty-list<string> $values
     * @param non-empty-list<int> $types
     */
    private function __construct(
        public readonly array $values,
        public readonly array $types,
    ) {
        $this->state = new PhpDocTokenState($this);
        $this->next();
    }

    public static function of(string $source): self
    {
        [$values, $types] = PhpDocLexer::tokenize($source);
        return new self($values, $types);
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
}
