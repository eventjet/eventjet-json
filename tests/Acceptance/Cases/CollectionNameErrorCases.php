<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use ReflectionException;
use RuntimeException;

/** @internal */
final class CollectionNameErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public static function errors(): iterable
    {
        yield from CollectionNameTypeErrorCases::errors();

        $person = new Person('Ada', 'Lovelace');
        $fixtures = 'Eventjet\\Json\\Test\\Acceptance\\Fixtures';
        $specifications = [
            'function import' => ['use function ' . $fixtures . '\\Person as Item;', 'Item', $person],
            'constant import' => ['use const ' . $fixtures . '\\Person as Item;', 'Item', $person],
            'group function import' => ['use ' . $fixtures . '\\{function Person as Item};', 'Item', $person],
            'group constant import' => ['use ' . $fixtures . '\\{const Person as Item};', 'Item', $person],
            'whole function group' => ['use function ' . $fixtures . '\\{Person as Item};', 'Item', $person],
            'whole constant group' => ['use const ' . $fixtures . '\\{Person as Item};', 'Item', $person],
            'unknown imported class' => ['use UnknownCollectionClass as Item;', 'Item', $person],
            'unknown namespace alias member' => [
                'use ' . $fixtures . ' as Models;',
                'Models\\UnknownCollectionClass',
                $person,
            ],
        ];
        foreach ($specifications as $label => $specification) {
            foreach (['param', 'var'] as $tag) {
                $original = CollectionNameFixture::create($specification, $tag, 'list', 'semicolon');
                $class = $original::class;
                $message =
                    'Could not create '
                    . $class
                    . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @'
                    . $tag
                    . ' with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.';
                foreach (['{}', '{"value":[]}'] as $json) {
                    yield $label . '/' . $tag . '/' . $json => [$json, $class, $message, 3];
                }
            }
        }

        foreach (['WrongContainer', 'ArrayObject'] as $alias) {
            foreach (['param', 'var'] as $tag) {
                $specification = [
                    'use ' . $fixtures . '\\Person as ' . $alias . ';',
                    '\\' . $fixtures . '\\Person',
                    $person,
                    $alias,
                ];
                $original = CollectionNameFixture::create($specification, $tag, 'ArrayObject', 'semicolon');
                $class = $original::class;
                foreach (['{}', '{"value":{}}'] as $json) {
                    yield 'wrong container/' . $alias . '/' . $tag . '/' . $json => [
                        $json,
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field value uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
                        3,
                    ];
                }
            }
        }
    }
}
