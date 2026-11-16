<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use function array_key_exists;
use function count;
use function implode;

/**
 * Scalar members and unions of them for {@see DecodableGrammarSource}.
 *
 * @internal
 * @psalm-import-type Value from DecodableGrammarSource
 * @phpstan-import-type Value from DecodableGrammarSource
 */
final class DecodableScalarSource
{
    /** @var non-empty-list<'int'|'float'|'string'|'bool'|'null'> */
    private const array TYPES = ['int', 'float', 'string', 'bool', 'null'];

    /**
     * Scalar members by JSON type, so that a union has at most one member of each JSON type.
     *
     * @var array{
     *     int: non-empty-list<array{string, string, int}>,
     *     float: non-empty-list<array{string, string, float}>,
     *     string: non-empty-list<array{string, string, string}>,
     *     bool: non-empty-list<array{string, string, bool}>,
     *     null: non-empty-list<array{string, string, null}>,
     * }
     */
    private const array SCALARS = [
        'int' => [['int', 'int', 3], ['42', '42', 42], ['-7', '-7', -7], ['1_000', '1000', 1000]],
        'float' => [
            ['float', 'float', 1.5],
            ['1.5',   '1.5',   1.5],
        ],
        'string' => [
            ['string', 'string', 'text'],
            ["'a'",    "'a'",    'a'],
            ['"b"',    '"b"',    'b'],
        ],
        'bool' => [
            ['bool',  'bool',  true],
            ['true',  'true',  true],
            ['false', 'false', false],
        ],
        'null' => [['null', 'null', null]],
    ];

    /** @return array{string, string, Value} */
    public static function scalar(): array
    {
        return ParserGrammarSource::pick(ParserGrammarSource::pick([
            self::SCALARS['int'],
            self::SCALARS['float'],
            self::SCALARS['string'],
            self::SCALARS['bool'],
        ]));
    }

    /**
     * An integer literal would overlap a float member, so a union with both uses the plain int type.
     *
     * @return array{string, string, Value}
     */
    public static function union(): array
    {
        $types = [];
        for ($attempt = ParserGrammarSource::count(2, 4); $attempt > 0; $attempt--) {
            $types[ParserGrammarSource::pick(self::TYPES)] = true;
        }
        if (count($types) < 2) {
            $types += ['null' => true, 'int' => true];
        }
        $chosen = ParserGrammarSource::count(0, count($types) - 1);
        $source = '';
        $printed = [];
        $value = null;
        foreach (self::SCALARS as $type => $members) {
            if (!array_key_exists($type, $types)) {
                continue;
            }
            $plain = $type === 'int' && array_key_exists('float', $types);
            [$memberSource, $memberPrinted, $memberValue] = ParserGrammarSource::pick(
                $plain ? [$members[0]] : $members,
            );
            $source .= $printed === [] ? '' : ParserGrammarSource::breakOrSpace() . '|' . ParserGrammarSource::space();
            $source .= $memberSource;
            $value = count($printed) === $chosen ? $memberValue : $value;
            $printed[] = $memberPrinted;
        }
        return [$source, implode('|', $printed), $value];
    }
}
