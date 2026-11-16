<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

/**
 * Reads the class escapes `\d`, `\s`, `\w`, `\p{…}` and their negations, after the backslash.
 *
 * @internal
 */
final readonly class ClassEscape
{
    /** WhiteSpace and LineTerminator of ECMA-262, which `\s` matches, as code point ranges. */
    private const array WHITESPACE = [
        [0x09, 0x0D],
        [0x20, 0x20],
        [0xA0, 0xA0],
        [0x1680, 0x1680],
        [0x2000, 0x200A],
        [0x2028, 0x2029],
        [0x202F, 0x202F],
        [0x205F, 0x205F],
        [0x3000, 0x3000],
        [0xFEFF, 0xFEFF],
    ];

    private const array DIGITS = [[0x30, 0x39]];

    private const array WORD = [[0x30, 0x39], [0x41, 0x5A], [0x5F, 0x5F], [0x61, 0x7A]];

    /** The ranges of each escape, and whether the escape matches what is outside them. */
    private const array SETS = [
        'd' => [self::DIGITS, false],
        'D' => [self::DIGITS, true],
        'w' => [self::WORD, false],
        'W' => [self::WORD, true],
        's' => [self::WHITESPACE, false],
        'S' => [self::WHITESPACE, true],
    ];

    public function __construct(
        private Cursor $cursor,
    ) {}

    /** Returns the escape as a PCRE atom, or null if there is no class escape here. */
    public function standalone(): string|null
    {
        $ranges = $this->ranges();
        return $ranges === null ? $this->property() : '[' . CodePoints::ranges($ranges) . ']';
    }

    /** Returns the escape for use inside a PCRE character class, or null if there is no class escape here. */
    public function member(): string|null
    {
        $ranges = $this->ranges();
        return $ranges === null ? $this->property() : CodePoints::ranges($ranges);
    }

    /** @return list<array{int, int}>|null */
    private function ranges(): array|null
    {
        $set = self::SETS[$this->cursor->peek() ?? ''] ?? null;
        if ($set === null) {
            return null;
        }
        $this->cursor->pos++;
        return $set[1] ? CodePoints::complement($set[0]) : $set[0];
    }

    private function property(): string|null
    {
        $char = $this->cursor->peek();
        if ($char !== 'p' && $char !== 'P' || $this->cursor->peek(offset: 1) !== '{') {
            return null;
        }
        $start = $this->cursor->pos;
        $this->cursor->pos += 2;
        $name = $this->cursor->until('}');
        $property = $name === null ? null : UnicodeProperties::resolve($name);
        if ($property === null) {
            $this->cursor->pos = $start;
            return null;
        }
        return '\\' . $char . '{' . $property . '}';
    }
}
