<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;

/** @internal */
final class CollectionsRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            foreach ([0, 1, 7] as $size) {
                $people = [];
                for ($index = 0; $index < $size; ++$index) {
                    $people[] = new Person('person-' . $index, 'family-' . $index, null, $index);
                }
                yield $tag . ' object list of size ' . $size => [
                    CollectionDeclarationFixture::object('array', 'list<\\' . Person::class . '>', $tag, $people),
                ];
            }
        }
        yield 'scalar lists preserve item types and values' => [new ScalarListFields(
            ['', '42', 'Grüße, 世界, 😀'],
            [0, -42, 42],
            [0.0, 3.0, -3.25],
            [true, false],
            ['float', 'float', 'float'],
        )];
        yield 'scalar maps distinguish integers from whole-valued floats' => [new ScalarMapFields(
            ['value' => 'text'],
            ['zero' => 0, 'negative' => -42],
            ['whole' => 3.0, 'fraction' => 3.25, 'negative' => -3.0],
            ['yes' => true, 'no' => false],
        )];
        yield 'tuple retains a fractional float through general item conversion' =>
            [CollectionDeclarationFixture::object('array', 'array{float}', 'param', [3.25])];
        foreach (['param', 'var'] as $tag) {
            foreach ([
                'string' => ['empty' => '', 'unicode' => 'Grüße, 世界, 😀'],
                'int' => ['zero' => 0, 'negative' => -42, 'positive' => 42],
                'bool' => ['yes' => true, 'no' => false],
                'float' => ['whole' => 3.0, 'fraction' => 3.25, 'negative' => -3.0],
            ] as $type => $values) {
                yield $tag . ' scalar ' . $type . ' map preserves values and types' =>
                    [CollectionDeclarationFixture::object(
                        'array',
                        'non-empty-array<string, ' . $type . '>',
                        $tag,
                        $values,
                    )];
                yield $tag . ' scalar ' . $type . ' ArrayObject preserves values and types' =>
                    [CollectionDeclarationFixture::object(
                        '\\ArrayObject',
                        'ArrayObject<string, ' . $type . '>',
                        $tag,
                        new ArrayObject($values),
                    )];
                yield $tag . ' empty scalar ' . $type . ' ArrayObject preserves its shape' =>
                    [CollectionDeclarationFixture::object(
                        '\\ArrayObject',
                        'ArrayObject<string, ' . $type . '>',
                        $tag,
                        new ArrayObject(),
                    )];
            }
        }
    }
}
