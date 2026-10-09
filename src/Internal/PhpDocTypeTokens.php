<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use UnexpectedValueException;

use function preg_match;
use function strlen;
use function strspn;
use function substr;

/** @internal */
final class PhpDocTypeTokens
{
    private const string IDENTIFIER_PATTERN = '/\G\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*(?:::[A-Za-z_*\x80-\xff][A-Za-z0-9_*\x80-\xff]*)?/';
    private const string NUMBER_PATTERN = '/\G[+-]?(?:0[xX][0-9a-fA-F]+(?:_[0-9a-fA-F]+)*|0[bB][01]+(?:_[01]+)*|0[oO][0-7]+(?:_[0-7]+)*|(?:[0-9]+(?:_[0-9]+)*(?:\.[0-9]*(?:_[0-9]+)*)?|\.[0-9]+(?:_[0-9]+)*)(?:[eE][+-]?[0-9]+(?:_[0-9]+)*)?)/';
    private const string SINGLE_QUOTED_PATTERN = '/\G\x27(?:[^\x27\\\\]|\\\\[\s\S])*\x27/';
    private const string DOUBLE_QUOTED_PATTERN = '/\G"(?:[^"\\\\]|\\\\[\s\S])*"/';

    private int $offset = 0;

    public function __construct(
        private readonly string $source,
    ) {}

    public function remainder(): string
    {
        return substr($this->source, $this->offset);
    }

    /**
     * @return array{string|null, bool}
     * @throws UnexpectedValueException
     */
    public function tupleKey(): array
    {
        $start = $this->offset;
        $key = $this->token();
        $optional = $this->consume('?');

        $hasKey = $this->consume(':');

        if ($hasKey) {
            return [$key, $optional];
        }

        $this->offset = $start;
        return [null, false];
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

    public function peek(): string|null
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
    private function pattern(): string
    {
        $first = $this->source[$this->offset] ?? '';
        return match ($first) {
            "'" => self::SINGLE_QUOTED_PATTERN,
            '"' => self::DOUBLE_QUOTED_PATTERN,
            '+', '-', '.' => self::NUMBER_PATTERN,
            default => $first >= '0' && $first <= '9' ? self::NUMBER_PATTERN : self::IDENTIFIER_PATTERN,
        };
    }
}
