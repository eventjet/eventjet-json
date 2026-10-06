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
            pattern: '/\G(?:-?[0-9]+|\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*)/',
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
}
