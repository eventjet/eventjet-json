<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function array_slice;
use function assert;
use function count;
use function hexdec;
use function implode;
use function is_int;
use function str_contains;

/**
 * The position in the code points of an ECMA-262 regex while it is being read.
 *
 * @internal
 */
final class Cursor
{
    public const string DECIMAL = '0123456789';
    public const string HEXADECIMAL = '0123456789ABCDEFabcdef';
    public const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public int $pos = 0;

    /** @param list<string> $chars */
    public function __construct(
        private readonly array $chars,
    ) {}

    /** @mutation-free */
    public function peek(int $offset = 0): string|null
    {
        return $this->chars[$this->pos + $offset] ?? null;
    }

    /** @mutation-free */
    public function atEnd(): bool
    {
        return $this->pos >= count($this->chars);
    }

    /** Reads the given number of code points. */
    public function take(int $length): string
    {
        $taken = implode('', array_slice($this->chars, $this->pos, $length));
        $this->pos += $length;
        return $taken;
    }

    /** Reads the code points that are in the given set, up to the first that isn't. */
    public function span(string $set): string
    {
        $span = '';
        while (true) {
            $char = $this->peek();
            if ($char === null) {
                return $span;
            }
            if (!str_contains($set, $char)) {
                return $span;
            }
            $span .= $char;
            $this->pos++;
        }
    }

    /** Reads exactly the given number of hexadecimal digits, or returns null if there are fewer. */
    public function hex(int $length): int|null
    {
        $hex = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $this->peek();
            if ($char === null) {
                return null;
            }
            if (!str_contains(self::HEXADECIMAL, $char)) {
                return null;
            }
            $hex .= $char;
            $this->pos++;
        }
        $value = hexdec($hex);
        assert(is_int($value), description: 'Four hex digits fit into an integer.');
        return $value;
    }

    /** Reads up to and including the given code point, and returns what is before it, or null if it doesn't come. */
    public function until(string $end): string|null
    {
        $read = '';
        while (true) {
            $char = $this->peek();
            if ($char === null) {
                return null;
            }
            $this->pos++;
            if ($char === $end) {
                return $read;
            }
            $read .= $char;
        }
    }
}
