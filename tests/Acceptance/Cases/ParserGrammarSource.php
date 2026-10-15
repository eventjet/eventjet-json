<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use function array_shift;
use function count;
use function mt_rand;

/**
 * Random choices and valid spacing for {@see ParserGrammarCases}.
 *
 * @internal
 */
final class ParserGrammarSource
{
    /** Horizontal whitespace, which is allowed between any two tokens. */
    private const array SPACES = ['', '', '', ' ', '  ', "\t", " \t "];

    /** Line breaks as they appear in a doc comment, which are allowed only at some places. */
    private const array LINE_BREAKS = [
        "\n",
        "\r\n",
        "\n * ",
        "\n     *  ",
        "\n\t*",
        "\n *",
        " // note\n * ",
        "\n // x\n * ",
    ];

    public static function chance(int $percent): bool
    {
        return mt_rand(min: 1, max: 100) <= $percent;
    }

    public static function count(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * @template T
     * @param non-empty-list<T> $items
     * @return T
     */
    public static function pick(array $items): mixed
    {
        $offset = mt_rand(min: 0, max: count($items) - 1);
        return $items[$offset] ?? $items[0];
    }

    public static function space(): string
    {
        return self::pick(self::SPACES);
    }

    public static function breakOrSpace(): string
    {
        $break = self::chance(25);
        return $break ? self::space() . self::pick(self::LINE_BREAKS) . self::space() : self::space();
    }

    /**
     * Joins items with commas, around which spaces and line breaks are allowed, and sometimes adds a trailing comma.
     *
     * @param list<string> $items
     */
    public static function list(array $items): string
    {
        $joined = array_shift($items) ?? '';
        $trailing = $joined !== '' && self::chance(25);
        foreach ($items as $item) {
            $joined .= self::breakOrSpace() . ',' . self::breakOrSpace() . $item;
        }
        return $trailing ? $joined . self::breakOrSpace() . ',' : $joined;
    }
}
