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
    /** A quoted string, a number, or a name with an optional class constant, such as `Foo::BAR`. */
    private const string PATTERN =
        '/\G(?:'
            . '\x27(?:[^\x27\\\\]|\\\\[\s\S])*\x27|"(?:[^"\\\\]|\\\\[\s\S])*"'
            . '|[+-]?(?:0[xX][0-9a-fA-F]+(?:_[0-9a-fA-F]+)*|0[bB][01]+(?:_[01]+)*|0[oO][0-7]+(?:_[0-7]+)*|(?:[0-9]+(?:_[0-9]+)*(?:\.[0-9]*(?:_[0-9]+)*)?|\.[0-9]+(?:_[0-9]+)*)(?:[eE][+-]?[0-9]+(?:_[0-9]+)*)?)'
            . '|\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*(?:::[A-Za-z_*\x80-\xff][A-Za-z0-9_*\x80-\xff]*)?'
            . ')/';

    private int $offset = 0;

    public function __construct(
        private readonly string $source,
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
            pattern: self::PATTERN,
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

    /** @throws UnexpectedValueException */
    public function expect(string $character): void
    {
        $consumed = $this->consume($character);
        if (!$consumed) {
            throw new UnexpectedValueException('Expected a closing PHPDoc delimiter.');
        }
    }

    private function whitespace(): void
    {
        $this->offset += strspn($this->source, characters: " \t\r\n\v\f", offset: $this->offset);
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
}
