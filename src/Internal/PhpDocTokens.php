<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_intersect;
use function array_key_last;
use function preg_match;
use function preg_match_all;
use function str_contains;

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

    /** The index of the last `(`, after which no callable can start, or null if there is none. */
    public readonly int|null $lastParenthesis;

    /** @var array<string, int>|null The index of the last token with a possible end tag, by element name. */
    private array|null $endTags = null;

    /**
     * @param non-empty-list<string> $values
     * @param non-empty-list<int> $types
     * @param bool $slash Whether the source has a slash between two other characters. An HTML end tag needs one,
     *     and the slashes that open and close a doc comment are not.
     */
    private function __construct(
        public readonly array $values,
        public readonly array $types,
        public readonly bool $slash,
    ) {
        $this->state = new PhpDocTokenState($this);
        $this->lastParenthesis = array_key_last(array_intersect($types, [PhpDocLexer::OPEN_PARENTHESES]));
        $this->next();
    }

    public static function of(string $source): self
    {
        [$values, $types] = PhpDocLexer::tokenize($source);
        return new self($values, $types, preg_match('~./.~', $source) === 1);
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

    /**
     * The index of the last token that contains `/name>`, as every end tag of the element does, or null if none does.
     * The tokens are searched once, so that checking every `<` does not walk to the end each time.
     */
    public function lastEndTag(string $name): int|null
    {
        if ($this->endTags === null) {
            $this->endTags = [];
            foreach ($this->values as $index => $value) {
                if (!str_contains($value, '/')) {
                    continue;
                }
                $matches = [];
                preg_match_all('~/([^/>]++)>~', $value, $matches);
                /** @var array{0: list<string>, 1: list<string>} $matches */
                $candidates = $matches[1];
                foreach ($candidates as $candidate) {
                    $this->endTags[$candidate] = $index;
                }
            }
        }
        return $this->endTags[$name] ?? null;
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
