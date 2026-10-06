<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class ConversionPathInputs
{
    /** @return iterable<string, array{string, string, string, string, string}> */
    public static function leaves(): iterable
    {
        yield 'scalar' => ['int', '', '"wrong"', '42', ' must be of type int, string given.'];
        yield 'nullable scalar' => ['?string', '', '42', 'null', ' must be of type string|null, int given.'];
        yield 'scalar union' => ['int|string', '', 'false', '42', ' must be of type string|int, bool given.'];
        yield 'class scalar union' => [
            '\\' . Person::class . '|string',
            '',
            'false',
            '"valid"',
            ' must be of type ' . Person::class . '|string, bool given.',
        ];
        yield 'enum scalar union' => [
            '\\' . StringBackedStatus::class . '|bool|null',
            '',
            '[]',
            'null',
            ' must be of type ' . StringBackedStatus::class . '|bool|null, array given.',
        ];
        yield 'unknown enum scalar union' => [
            '\\' . StringBackedStatus::class . '|bool|null',
            '',
            '"unknown"',
            'null',
            ' uses backed enum ' . StringBackedStatus::class . ", which has no case with backing value 'unknown'.",
        ];
        yield 'unknown enum value' => [
            '\\' . StringBackedStatus::class,
            '',
            '"unknown"',
            '"ready"',
            ' uses backed enum ' . StringBackedStatus::class . ", which has no case with backing value 'unknown'.",
        ];
        yield 'enum backing type' => [
            '\\' . StringBackedStatus::class,
            '',
            '42',
            '"ready"',
            ' uses backed enum ' . StringBackedStatus::class . ', which expects a string backing value; int given.',
        ];
        foreach (CollectionUnionCases::shapes('int|string') as $shape => [$native, $declaration]) {
            $map = $shape === 'map' || $shape === 'non-empty-map' || $shape === 'ArrayObject';
            yield 'collection ' . $shape => [
                $native,
                $declaration,
                $map ? '{"key":false}' : '[false]',
                $map ? '{"key":42}' : '[42]',
                ($map ? '[key]' : '[0]') . ' must be of type int|string, bool given.',
            ];
        }
        yield 'list shape' => ['array', 'list<int>', '{}', '[]', ' must be of type list<int>, stdClass given.'];
        yield 'nonempty list' => [
            'array',
            'non-empty-list<int>',
            '[]',
            '[1]',
            ' must be of type non-empty-list<int>, empty list given.',
        ];
        yield 'map shape' => [
            'array',
            'non-empty-array<string, int>',
            '[]',
            '{"key":1}',
            ' must be of type JSON object, array given.',
        ];
        yield 'tuple length' => [
            'array',
            'array{int}',
            '[]',
            '[1]',
            ' must be of type array{int} with exactly 1 items, 0 given.',
        ];
    }

    /**
     * @param class-string $class
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function wrappers(string $class): iterable
    {
        yield 'object' => ['\\' . $class, '', '', '', ''];
        yield 'object union' => ['\\' . $class . '|int|null', '', '', '', ''];
        foreach (CollectionUnionCases::shapes('\\' . $class . '|int|null') as $shape => [$native, $declaration]) {
            $map = $shape === 'map' || $shape === 'non-empty-map' || $shape === 'ArrayObject';
            yield $shape => [$native, $declaration, $map ? '{"key":' : '[', $map ? '}' : ']', $map ? '[key]' : '[0]'];
        }
    }
}
