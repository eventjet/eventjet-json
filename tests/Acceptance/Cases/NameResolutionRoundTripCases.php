<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class NameResolutionRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::nameResolution1();
        yield from self::nameResolution2();
        yield from self::nameResolution3();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nameResolution1(): iterable
    {
        yield 'import/param/list/same-line declarations' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'same-line declarations',
        )];
        yield 'namespace relative uppercase/param/list/semicolon' => [CollectionNameFixture::create(
            ['', 'NAMESPACE\LocalPerson', null],
            'param',
            'list',
            'semicolon',
        )];
        yield 'group alias/param/list/semicolon' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\{Person, StringBackedStatus as Status};',
                'Status',
                StringBackedStatus::Ready,
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'long namespace alias/param/list/semicolon' => [CollectionNameFixture::create(
            ['use Eventjet as Root;', 'Root\Json\Test\Acceptance\Fixtures\Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'uppercase function group shadow/param/list/semicolon' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\Person; use FUNCTION Eventjet\Json\Test\Acceptance\Fixtures\{Person};',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'property tag formatting /** @var list<int>*/' => [CollectionDeclarationFixture::object(
            'array',
            '/** @var list<int>*/',
            'var',
            [42],
        )];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nameResolution2(): iterable
    {
        yield 'parameter mentioned in description ?int 0a' => [CollectionDeclarationFixture::object(
            'array',
            '/**
 * @param ?int $count Number of elements in $value
 * @param list<int> $value The actual collection
 */',
            'param',
            [42],
        )];
        yield 'fully qualified/param/list/semicolon' => [CollectionNameFixture::create(
            ['', '\Eventjet\Json\Test\Acceptance\Fixtures\Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'import/param/list/global' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'global',
        )];
        yield 'import/param/list/same-line classes' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'same-line classes',
        )];
        yield 'uppercase alias keyword/param/list/semicolon' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person AS Model;', 'Model', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'preceding interpolation/param/list/semicolon' => [CollectionNameFixture::create(
            [
                '$text = "brace"; $string = "{$text} {"; use Eventjet\Json\Test\Acceptance\Fixtures\Person;',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nameResolution3(): iterable
    {
        yield 'legacy interpolation/param/list/semicolon' => [CollectionNameFixture::create(
            [
                '$text = "brace"; $string = "${text} {"; use Eventjet\Json\Test\Acceptance\Fixtures\Person;',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'mixed group/param/list/semicolon' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\{function strlen, const ANSWER, Person as Model};',
                'Model',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'preceding closure/param/list/semicolon' => [CollectionNameFixture::create(
            [
                '$captured = 42; $closure = function () use ($captured) {return $captured;}; use Eventjet\Json\Test\Acceptance\Fixtures\Person;',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
    }
}
