<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use PhpToken;

/** @internal */
final class PhpDocTokenStream
{
    /** @var array<PhpToken> */
    private readonly array $tokens;

    private int $index = 0;

    public function __construct(string $source)
    {
        $this->tokens = PhpToken::tokenize($source);
    }

    public function current(): PhpToken|null
    {
        return $this->tokens[$this->index] ?? null;
    }

    public function advance(): void
    {
        $this->index++;
    }
}
