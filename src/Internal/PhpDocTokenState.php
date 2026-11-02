<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;

/**
 * Holds the save points of a token walk and the comments consumed inside types, like phpstan/phpdoc-parser's token
 * iterator. Parsers that throw between pushing and dropping a save point leave it on the stack, as phpdoc-parser does,
 * so a later rollback can restore an earlier position than the one it saved.
 *
 * @internal
 */
final class PhpDocTokenState
{
    /** Whether comments were consumed inside types and not yet attached to a type, which phpdoc-parser forbids. */
    private bool $comments = false;

    /** @var list<array{int, bool}> */
    private array $savePoints = [];

    public function __construct(
        private readonly PhpDocTokens $tokens,
    ) {}

    public function push(): void
    {
        $this->savePoints[] = [$this->tokens->index, $this->comments];
    }

    public function drop(): void
    {
        array_pop($this->savePoints);
    }

    public function rollback(): void
    {
        $savePoint = array_pop($this->savePoints);
        if ($savePoint !== null) {
            [$this->tokens->index, $this->comments] = $savePoint;
        }
    }

    /** Skips line ends inside a type, together with any comments before or between them. */
    public function skipLineEnds(): void
    {
        $tokens = $this->tokens;
        $this->skipComment();
        $found = $tokens->is(PhpDocLexer::PHPDOC_EOL);
        while ($found) {
            $found = $tokens->tryConsume(PhpDocLexer::PHPDOC_EOL);
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

    private function skipComment(): void
    {
        if ($this->tokens->is(PhpDocLexer::COMMENT)) {
            $this->tokens->next();
            $this->comments = true;
        }
    }
}
