<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;

use function mt_srand;

/**
 * Generates types from the grammar that the decoder reads, prints each with random but valid spacing, line breaks,
 * comments, parentheses, and trailing commas, and expects the parser to return the generated syntax tree.
 *
 * @internal
 */
final class ParserGrammarCases
{
    private const array NAMES = [
        'int',
        'string',
        'list',
        'non-empty-list',
        'array',
        'Foo',
        '\\Foo\\Bar',
        'Foo\\Bar_9',
        'namespace\\Foo',
        '_foo',
        'a-b-',
        "Caf\u{e9}",
        'Z',
        'null',
        'true',
        'FALSE',
        'is',
        'not',
        'covariantly',
        'min',
        'max',
    ];

    /** Pairs of a constant expression and the name that the parser returns for it. */
    private const array CONSTANTS = [
        ['42',                                  '42'],
        ['-7',                                  '-7'],
        ['+3',                                  '+3'],
        ['1_000',                               '1000'],
        ['0',                                   '0'],
        ['0x1F',                                '0x1F'],
        ['0X1f',                                '0X1f'],
        ['0b101',                               '0b101'],
        ['0B1_0',                               '0B10'],
        ['0o17',                                '0o17'],
        ['0O7',                                 '0O7'],
        ['017',                                 '017'],
        ['1.5',                                 '1.5'],
        ['.5',                                  '.5'],
        ['1.',                                  '1.'],
        ['-1e3',                                '-1e3'],
        ['1E+3',                                '1E+3'],
        ['1_0.5_0e-1_0',                        '10.50e-10'],
        ["'foo'",                               "'foo'"],
        ["''",                                  "''"],
        ['""',                                  '""'],
        ["'it\\'s'",                            "'it\\'s'"],
        ["'a\\\\b'",                            "'a\\\\b'"],
        ["'a\\nb'",                             "'a\\\\nb'"],
        ["'\"'",                                "'\"'"],
        ['"a\\"b"',                             '"a\\"b"'],
        ['"\\n\\t\\r\\v\\f\\e"',                "\"\n\t\r\v\f\e\""],
        ['"\\x41\\X4a\\101\\0"',                "\"AJA\0\""],
        ['"\\u{41}\\u{e9}\\u{20ac}\\u{1f600}"', "\"A\u{e9}\u{20ac}\u{1f600}\""],
        ['"\\$a\\q\\\\"',                       '"\\\\$a\\\\q\\\\"'],
        ["'\\u{41}'",                           "'\\\\u{41}'"],
        ['"<>{}|,()"',                          '"<>{}|,()"'],
        ['Foo::BAR',                            'Foo::BAR'],
        ['\\Foo\\Bar::BAZ_9',                   '\\Foo\\Bar::BAZ_9'],
        ['Foo::BAR_*',                          'Foo::BAR_*'],
        ['Foo::*',                              'Foo::*'],
        ['Foo::*_BAR',                          'Foo::*_BAR'],
        ['Foo::A*B',                            'Foo::A*B'],
        ['self::class',                         'self::class'],
        ['Foo :: BAR',                          'Foo::BAR'],
        ['Foo::is',                             'Foo::is'],
    ];

    private const array SHAPES = ['array', 'list', 'non-empty-array', 'non-empty-list'];

    /** Pairs of a shape key and the name that the parser returns for it. */
    private const array KEYS = [
        ['0',        '0'],
        ['1_2',      '12'],
        ['-1',       '-1'],
        ['0x1',      '0x1'],
        ['foo',      'foo'],
        ['Foo\\Bar', 'Foo\\Bar'],
        ["'a b'",    "'a b'"],
        ["'it\\'s'", "'it\\'s'"],
        ['"\\x41"',  '"A"'],
        ['Foo::BAR', 'Foo::BAR'],
        ['int',      'int'],
        ['list',     'list'],
    ];

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, PhpDocType}>
     */
    public static function types(): iterable
    {
        mt_srand(20_261_013);
        for ($index = 0; $index < 600; $index++) {
            [$source, $tree] = self::type(0, ParserGrammarSource::space(...));
            yield 'generated type ' . $index => [$source, $tree];
        }
    }

    /**
     * Returns a source and its tree. Inside parentheses, a union may break lines after an operator too.
     *
     * @param callable(): string $afterOperator
     * @return array{string, PhpDocType}
     */
    private static function type(int $depth, callable $afterOperator): array
    {
        [$source, $tree] = self::atomic($depth);
        $compound = $depth <= 3 && ParserGrammarSource::chance(35);
        if (!$compound) {
            return [$source, $tree];
        }
        $intersection = ParserGrammarSource::chance(30);
        $operator = $intersection ? '&' : '|';
        $trees = [$tree];
        for ($member = ParserGrammarSource::count(1, 2); $member > 0; $member--) {
            [$next, $trees[]] = self::atomic($depth);
            $source .= ParserGrammarSource::breakOrSpace() . $operator . $afterOperator() . $next;
        }
        return [$source, new PhpDocType($operator, $trees)];
    }

    /** @return array{string, PhpDocType} */
    private static function atomic(int $depth): array
    {
        $roll = $depth > 3 ? 1 : ParserGrammarSource::count(1, 10);
        return match (true) {
            $roll <= 3 => self::name(),
            $roll <= 5 => self::constant(),
            $roll <= 7 => self::generic($depth),
            $roll <= 9 => self::shape($depth),
            default => self::parenthesized($depth),
        };
    }

    /** @return array{string, PhpDocType} */
    private static function name(): array
    {
        $name = ParserGrammarSource::pick(self::NAMES);
        return [$name, new PhpDocType($name)];
    }

    /** @return array{string, PhpDocType} */
    private static function constant(): array
    {
        [$source, $name] = ParserGrammarSource::pick(self::CONSTANTS);
        return [$source, new PhpDocType($name)];
    }

    /** @return array{string, PhpDocType} */
    private static function parenthesized(int $depth): array
    {
        [$source, $tree] = self::type($depth + 1, ParserGrammarSource::breakOrSpace(...));
        $space = ParserGrammarSource::breakOrSpace(...);
        return ['(' . $space() . $source . $space() . ')', $tree];
    }

    /** @return array{string, PhpDocType} */
    private static function generic(int $depth): array
    {
        $name = ParserGrammarSource::pick(self::NAMES);
        $sources = [];
        $trees = [];
        for ($argument = ParserGrammarSource::count(1, 3); $argument > 0; $argument--) {
            [$sources[], $trees[]] = self::type($depth + 1, ParserGrammarSource::space(...));
        }
        $space = ParserGrammarSource::breakOrSpace(...);
        $source = $name . '<' . $space() . ParserGrammarSource::list($sources) . $space() . '>';
        return [$source, new PhpDocType($name, $trees)];
    }

    /** @return array{string, PhpDocType} */
    private static function shape(int $depth): array
    {
        $kind = ParserGrammarSource::pick(self::SHAPES);
        $sources = [];
        $entries = [];
        for ($entry = ParserGrammarSource::count(0, 3); $entry > 0; $entry--) {
            [$source, $tree] = self::type($depth + 1, ParserGrammarSource::space(...));
            $keyed = ParserGrammarSource::chance(50);
            [$key, $name] = $keyed ? ParserGrammarSource::pick(self::KEYS) : ['', null];
            $optional = $keyed && ParserGrammarSource::chance(30);
            $space = ParserGrammarSource::space(...);
            $prefix = $key . $space() . ($optional ? '?' . $space() : '') . ':' . $space();
            $sources[] = ($keyed ? $prefix : '') . $source;
            $entries[] = new PhpDocTupleEntry($tree, $name, $optional);
        }
        $space = ParserGrammarSource::breakOrSpace(...);
        $source = $kind . '{' . $space() . ParserGrammarSource::list($sources) . $space() . '}';
        return [$source, new PhpDocType($kind . '{}', entries: $entries)];
    }
}
