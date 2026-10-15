<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\DecodeErrorKind;
use ReflectionException;
use RuntimeException;

/**
 * `array-key` stands for `int|string`. Keywords are case-sensitive, and synonyms such as `integer` are not keywords.
 * `static`, `parent`, `number`, and `scalar` are unknown names, like any other.
 *
 * @internal
 */
final class PhpDocKeywordCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach ([
            ['array', 'list<array-key>', [5, 'a']],
            ['array', 'list<array-key|null>', [null, 'a', 0]],
            ['array', 'non-empty-list<int|array-key>', ['', 7]],
            ['array', 'array{array-key, array-key}', [-1, '-1']],
            ['ArrayObject', 'ArrayObject<string, array-key>', new ArrayObject(['a' => 1, 'b' => 'c'])],
            ['array|int|string', 'list<int>|array-key', 'a'],
            ['array|int|string', 'list<int>|array-key', 5],
            ['array|int|string', 'array-key|list<int>', [5]],
            ['int|string', "array-key|'a'", 'b'],
            ['int', 'int|static', 5],
            ['int', 'parent|int', 5],
            ['float', 'float|number', 1.5],
            ['string', 'scalar|string', 'a'],
        ] as $index => [$native, $declaration, $value]) {
            foreach (['param', 'var'] as $tag) {
                yield 'PHPDoc keyword ' . $index . ' ' . $declaration . ' in ' . $tag => [
                    CollectionDeclarationFixture::object($native, $declaration, $tag, $value),
                ];
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        $keywords = [
            'Int',
            'INT',
            'integer',
            'Bool',
            'boolean',
            'Float',
            'double',
            'String',
            'Array-Key',
            'ARRAY-KEY',
            'array_key',
            'Self',
            'NonEmptyString',
            'Non-Empty-String',
            'Positive-Int',
            'List<int>',
            'Non-Empty-List<int>',
            'Array{int}',
            'Non-Empty-Array<string, int>',
            'Non-Empty-Map<string, int>',
            'Int<0, max>',
            'int<MIN, max>',
            'int<min, Max>',
            'static',
            'parent',
            'number',
            'scalar',
        ];
        foreach ($keywords as $keyword) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', 'list<' . $keyword . '>', $tag);
                yield 'PHPDoc keyword is not recognized: list<' . $keyword . '> in ' . $tag => [
                    '{"value":[]}',
                    $class,
                    PhpDocReadingCases::unrecognized($class, $tag),
                    DecodeErrorKind::UnsupportedDeclaration->value,
                ];
            }
        }
        foreach (['non-empty-array<array-key, int>', 'ArrayObject<array-key, int>'] as $declaration) {
            $native = $declaration[0] === 'A' ? 'ArrayObject' : 'array';
            $class = CollectionDeclarationFixture::create($native, $declaration, 'param');
            yield 'array-key cannot be a map key: ' . $declaration => [
                '{"value":{}}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses unsupported map declaration '
                    . ($native === 'array' ? 'non-empty-array' : ArrayObject::class)
                    . '. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
                DecodeErrorKind::UnsupportedDeclaration->value,
            ];
        }
    }
}
