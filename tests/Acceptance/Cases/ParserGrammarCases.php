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

    private const array SHAPES = ['array', 'list', 'non-empty-array', 'non-empty-list'];

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
        return ParserGrammarSource::pick(ParserGrammarConstants::constants());
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
            [$key, $name] = $keyed ? ParserGrammarSource::pick(ParserGrammarConstants::keys()) : ['', null];
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
