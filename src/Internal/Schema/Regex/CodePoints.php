<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function dechex;
use function max;
use function min;
use function ord;
use function sprintf;
use function strlen;

/**
 * Turns code points and ranges of them into PCRE.
 *
 * @internal
 */
final class CodePoints
{
    /** @pure */
    public static function literal(int $codePoint): string
    {
        return sprintf('\x{%s}', dechex($codePoint));
    }

    /**
     * @param list<array{int, int}> $ranges
     * @pure
     */
    public static function ranges(array $ranges): string
    {
        $out = '';
        foreach ($ranges as [$from, $to]) {
            $out .= $from === $to ? self::literal($from) : self::literal($from) . '-' . self::literal($to);
        }
        return $out;
    }

    /**
     * @param list<array{int, int}> $ranges
     * @return list<array{int, int}>
     * @pure
     */
    public static function complement(array $ranges): array
    {
        $complement = [];
        $next = 0;
        foreach ($ranges as [$from, $to]) {
            $complement[] = [$next, $from - 1];
            $next = $to + 1;
        }
        $complement[] = [$next, 0x10_FFFF];
        return $complement;
    }

    /** @pure */
    public static function isSurrogate(int $codePoint): bool
    {
        return $codePoint >= 0xD800 && $codePoint <= 0xDFFF;
    }

    /**
     * Returns the parts of the range that aren't surrogates, which can't occur in valid UTF-8.
     *
     * @return list<array{int, int}>
     * @pure
     */
    public static function withoutSurrogates(int $from, int $to): array
    {
        $ranges = [];
        if ($from < 0xD800) {
            $ranges[] = [$from, min($to, 0xD7FF)];
        }
        if ($to > 0xDFFF) {
            $ranges[] = [max($from, 0xE000), $to];
        }
        return $ranges;
    }

    /**
     * Returns the code point of one UTF-8 encoded character.
     *
     * @pure
     */
    public static function of(string $char): int
    {
        $bytes = strlen($char);
        $first = ord($char[0]);
        if ($bytes === 1) {
            return $first;
        }
        // Subtracts the length prefix (110, 1110 or 11110) from the lead byte.
        $codePoint = $first - (0x100 - (1 << (8 - $bytes)));
        for ($i = 1; $i < $bytes; $i++) {
            $codePoint = ($codePoint << 6) | (ord($char[$i]) & 0x3F);
        }
        return $codePoint;
    }
}
