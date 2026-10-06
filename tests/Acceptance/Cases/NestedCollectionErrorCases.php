<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use function str_repeat;

/** @internal */
final class NestedCollectionErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from NestedCollectionShapeErrors::errors();
        foreach (self::invalidValues() as [$type, $json, $reason]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', 'list<' . $type . '>', $tag);
                yield 'nested conversion ' . $type . $json . $tag => [
                    '{"value":[' . $json . ']}',
                    $class,
                    'Could not create '
                        . ($type === 'list<\\' . Coordinates::class . '>' ? Coordinates::class : $class)
                        . ' from the JSON object: Field value[0]'
                        . $reason,
                    3,
                ];
            }
        }

        yield from NestedCollectionDeclarationErrors::errors();

        foreach ([2, 3, 6] as $depth) {
            $type = str_repeat('list<', $depth) . 'int' . str_repeat('>', $depth);
            $json = '"42"';
            for ($level = 0; $level < $depth; ++$level) {
                $json = '[' . $json . ']';
            }
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', $type, $tag);
                yield 'nested full leaf path ' . $depth . $tag => [
                    '{"value":' . $json . '}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value'
                        . str_repeat('[0]', $depth)
                        . ' must be of type int, string given.',
                    3,
                ];
            }
        }
    }

    /** @return iterable<array{string, string, string}> */
    private static function invalidValues(): iterable
    {
        yield ['list<int>', '{}', ' must be of type list<int>, stdClass given.'];
        yield ['list<int>', 'null', ' must be of type list<int>, null given.'];
        yield ['list<int>|null', '42', ' must be of type list<int>, int given.'];
        yield ['list<int>', '{"0":42}', ' must be of type list<int>, stdClass given.'];
        yield ['list<int>', '[42,"42"]', '[1] must be of type int, string given.'];
        yield ['non-empty-list<int>', '[]', ' must be of type non-empty-list<int>, empty list given.'];
        yield ['ArrayObject<string, int>', '[]', ' must be of type JSON object, array given.'];
        yield [
            'non-empty-array<string, int>',
            '{}',
            ' uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
        ];
        yield [
            'ArrayObject<string, int>',
            '{"0":42}',
            ' has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
        ];
        yield ['ArrayObject<string, int>', '{"01":42,"a.b":"42"}', '["a.b"] must be of type int, string given.'];
        yield ['list<int|null>', '[true]', '[0] must be of type int|null, bool given.'];
        yield [
            'list<\\' . StringBackedStatus::class . '>',
            '["unknown"]',
            '[0] uses backed enum ' . StringBackedStatus::class . ", which has no case with backing value 'unknown'.",
        ];
        yield [
            'list<\\' . Coordinates::class . '>',
            '[{"latitude":true,"longitude":3}]',
            '[0].latitude must be of type float, bool given.',
        ];
    }
}
