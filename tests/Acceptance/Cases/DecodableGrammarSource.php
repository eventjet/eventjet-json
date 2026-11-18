<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function implode;
use function mt_srand;

/**
 * Generates collection declarations that the decoder supports, prints each with random but valid spacing, line breaks,
 * comments, parentheses, and trailing commas, and generates a value of the type.
 *
 * @internal
 * @phpstan-type Value bool|float|int|string|StringBackedStatus|array<array-key, mixed>|null
 * @psalm-type Value = bool|float|int|string|StringBackedStatus|array<array-key, mixed>|null
 */
final class DecodableGrammarSource
{
    private const int COUNT = 300;

    /**
     * Generates all cases at once, because the random sequence must not depend on how the caller consumes them.
     *
     * @return array<string, array{string, string, Value, 'param'|'var'}>
     */
    public static function generated(): array
    {
        mt_srand(20_261_116);
        $cases = [];
        for ($index = 0; $index < self::COUNT; $index++) {
            [$source, $printed, $value] = self::collection(0);
            $param = ParserGrammarSource::chance(50);
            $tag = $param ? 'param' : 'var';
            $cases['decodable generated type ' . $index] = [$source, $printed, $value, $tag];
        }
        return $cases;
    }

    /** @return array{string, string, Value} */
    private static function collection(int $depth): array
    {
        $list = ParserGrammarSource::chance(70);
        return $list ? self::list($depth) : self::tuple($depth);
    }

    /** @return array{string, string, Value} */
    private static function item(int $depth): array
    {
        $roll = $depth >= 3 ? 1 : ParserGrammarSource::count(1, 10);
        [$source, $printed, $value] = match (true) {
            $roll <= 3 => DecodableScalarSource::scalar(),
            $roll <= 5 => DecodableScalarSource::union(),
            $roll <= 6 => self::enum(),
            $roll <= 8 => self::collection($depth + 1),
            default => self::map($depth + 1),
        };
        $parenthesized = ParserGrammarSource::chance(15);
        return [$parenthesized ? self::parenthesized($source) : $source, $printed, $value];
    }

    /** @return array{string, string, Value} */
    private static function enum(): array
    {
        $enum = '\\' . StringBackedStatus::class;
        return ParserGrammarSource::pick([
            [$enum, StringBackedStatus::class, StringBackedStatus::Pending],
            [$enum . '::Ready', $enum . '::Ready', StringBackedStatus::Ready],
            [
                '\\'
                    . ExtendedLiteralFields::class
                    . ParserGrammarSource::space()
                    . '::'
                    . ParserGrammarSource::space()
                    . 'NUMBER',
                '42',
                ExtendedLiteralFields::NUMBER,
            ],
        ]);
    }

    /** @return array{string, string, Value} */
    private static function list(int $depth): array
    {
        $name = ParserGrammarSource::pick(['list', 'non-empty-list']);
        [$source, $printed, $value] = self::item($depth);
        $empty = ParserGrammarSource::chance(30);
        $repeated = ParserGrammarSource::chance(40);
        $values = $name === 'list' && $empty ? [] : [$value];
        if ($repeated) {
            $values[] = $value;
        }
        $space = ParserGrammarSource::breakOrSpace(...);
        return [
            $name
                . ParserGrammarSource::space()
                . '<'
                . $space()
                . ParserGrammarSource::list([$source])
                . $space()
                . '>',
            $name . '<' . $printed . '>',
            $values,
        ];
    }

    /** @return array{string, string, Value} */
    private static function tuple(int $depth): array
    {
        $keyed = ParserGrammarSource::chance(30);
        $sources = [];
        $printed = [];
        $values = [];
        for ($entry = 0, $count = ParserGrammarSource::count(0, 3); $entry < $count; $entry++) {
            [$source, $entryPrinted, $values[]] = self::item($depth + 1);
            $printed[] = $depth === 0 ? $entryPrinted : $entry . ': ' . $entryPrinted;
            $space = ParserGrammarSource::space(...);
            $sources[] = $keyed ? $entry . $space() . ':' . $space() . $source : $source;
        }
        $space = ParserGrammarSource::breakOrSpace(...);
        return [
            'array{' . $space() . ParserGrammarSource::list($sources) . $space() . '}',
            'array{' . implode(', ', $printed) . '}',
            $values,
        ];
    }

    /** @return array{string, string, Value} */
    private static function map(int $depth): array
    {
        [$source, $printed, $value] = self::item($depth);
        $space = ParserGrammarSource::breakOrSpace(...);
        return [
            'non-empty-array<' . $space() . ParserGrammarSource::list(['string', $source]) . $space() . '>',
            'non-empty-array<string, ' . $printed . '>',
            ['k' => $value],
        ];
    }

    private static function parenthesized(string $source): string
    {
        $space = ParserGrammarSource::breakOrSpace(...);
        return '(' . $space() . $source . $space() . ')';
    }
}
