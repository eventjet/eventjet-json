<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use UnexpectedValueException;

use function preg_match;
use function strlen;
use function strspn;
use function substr;

/** @internal */
class PhpDocTypeTokens
{
    protected int $offset = 0;

    public function __construct(
        protected readonly string $source,
    ) {}

    public function remainder(): string
    {
        return substr($this->source, $this->offset);
    }

    /** @throws UnexpectedValueException */
    public function token(): string
    {
        $this->whitespace();
        $matches = [];
        $matched = preg_match(
            pattern: $this->pattern(),
            subject: $this->source,
            matches: $matches,
            flags: 0,
            offset: $this->offset,
        );

        if ($matched !== 1) {
            throw new UnexpectedValueException('Expected a PHPDoc type name.');
        }

        /** @var array{non-empty-string} $matches */
        $token = $matches[0];
        $this->offset += strlen($token);
        return $token;
    }

    private function peek(): string|null
    {
        $offset = $this->offset + strspn($this->source, characters: " \t\r\n\v\f", offset: $this->offset);
        return $this->source[$offset] ?? null;
    }

    public function consume(string $character): bool
    {
        $next = $this->peek();

        if ($next !== $character) {
            return false;
        }

        $this->whitespace();
        $this->offset++;
        return true;
    }

    private function whitespace(): void
    {
        $this->offset += strspn($this->source, characters: " \t\r\n\v\f", offset: $this->offset);
    }

    /** @return non-empty-string */
    protected function pattern(): string
    {
        return '/\G(?:-?[0-9]+|\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*)/';
    }
}
