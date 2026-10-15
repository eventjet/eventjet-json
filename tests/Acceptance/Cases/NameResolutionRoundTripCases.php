<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class NameResolutionRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::imports();
        yield from self::namespaceNames();
        yield from self::surroundingSyntax();
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function imports(): iterable
    {
        yield 'grouped class import resolves an enum alias' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\{Person, StringBackedStatus as Status};',
                'Status',
                StringBackedStatus::Ready,
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'namespace alias resolves a qualified class name' => [CollectionNameFixture::create(
            ['use Eventjet as Root;', 'Root\Json\Test\Acceptance\Fixtures\Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'function import does not shadow a class import' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\Person; use FUNCTION Eventjet\Json\Test\Acceptance\Fixtures\{Person};',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'class alias accepts an uppercase AS keyword' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person AS Model;', 'Model', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'mixed function, constant, and class import resolves the class alias' => [CollectionNameFixture::create(
            [
                'use Eventjet\Json\Test\Acceptance\Fixtures\{function strlen, const ANSWER, Person as Model};',
                'Model',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function namespaceNames(): iterable
    {
        yield 'namespace-relative class name accepts an uppercase keyword' => [CollectionNameFixture::create(
            ['', 'NAMESPACE\LocalPerson', null],
            'param',
            'list',
            'semicolon',
        )];
        yield 'fully qualified class name resolves without an import' => [CollectionNameFixture::create(
            ['', '\Eventjet\Json\Test\Acceptance\Fixtures\Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'semicolon',
        )];
        yield 'class import resolves in the global namespace' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'global',
        )];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function surroundingSyntax(): iterable
    {
        yield 'class import resolves beside same-line declarations' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'same-line declarations',
        )];
        yield 'class import resolves beside same-line classes' => [CollectionNameFixture::create(
            ['use Eventjet\Json\Test\Acceptance\Fixtures\Person;', 'Person', new Person('Ada', 'Lovelace')],
            'param',
            'list',
            'same-line classes',
        )];
        yield 'class import resolves after interpolated braces' => [CollectionNameFixture::create(
            [
                '$text = "brace"; $string = "{$text} {"; use Eventjet\Json\Test\Acceptance\Fixtures\Person;',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'class import resolves after legacy string interpolation' => [CollectionNameFixture::create(
            [
                '$text = "brace"; $string = "${text} {"; use Eventjet\Json\Test\Acceptance\Fixtures\Person;',
                'Person',
                new Person('Ada', 'Lovelace'),
            ],
            'param',
            'list',
            'semicolon',
        )];
        yield 'class import resolves after a closure use clause' => [CollectionNameFixture::create(
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
