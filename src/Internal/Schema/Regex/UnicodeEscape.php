<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function hexdec;

/**
 * Reads the code point of a `\u` escape, after the `u`.
 *
 * @internal
 */
final readonly class UnicodeEscape
{
    public function __construct(
        private Cursor $cursor,
    ) {}

    public function read(): int|null
    {
        if ($this->cursor->peek() === '{') {
            return $this->braced();
        }
        $high = $this->cursor->hex(length: 4);
        if ($high === null) {
            return null;
        }
        $pair =
            $high >= 0xD800
            && $high <= 0xDBFF
            && $this->cursor->peek() === '\\'
            && $this->cursor->peek(offset: 1) === 'u';
        return $pair ? $this->surrogatePair($high) : $high;
    }

    private function braced(): int|null
    {
        $this->cursor->pos++;
        $hex = $this->cursor->span(Cursor::HEXADECIMAL);
        if ($this->cursor->peek() !== '}' || $hex === '' || hexdec($hex) > 0x10_FFFF) {
            return null;
        }
        $this->cursor->pos++;
        return (int) hexdec($hex);
    }

    /** Joins a high surrogate with the low surrogate of a following `\u` escape, or keeps it alone if there is none. */
    private function surrogatePair(int $high): int
    {
        $start = $this->cursor->pos;
        $this->cursor->pos += 2;
        $low = $this->cursor->hex(length: 4);
        if ($low !== null && $low >= 0xDC00 && $low <= 0xDFFF) {
            return 0x1_0000 + (($high - 0xD800) << 10) + ($low - 0xDC00);
        }
        $this->cursor->pos = $start;
        return $high;
    }
}
