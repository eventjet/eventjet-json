<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use RuntimeException;

use function bin2hex;

/** @internal */
final class ParserCompoundCases
{
    /** @return iterable<string, array{string, PhpDocType}> */
    public static function types(): iterable
    {
        foreach (['|', '&'] as $operator) {
            foreach (['', ' ', "\r\n"] as $space) {
                $source = 'int' . $space . $operator . $space . 'string';
                $type = new PhpDocType($operator, [new PhpDocType('int'), new PhpDocType('string')]);
                yield 'compound ' . bin2hex($source) => [$source, $type];
                yield 'map compound ' . bin2hex($source) => [
                    'array<string, ' . $source . '>',
                    new PhpDocType('array', [new PhpDocType('string'), $type]),
                ];
            }
        }
        foreach (['|', '&'] as $operator) {
            yield 'optional tuple compound ' . $operator => [
                'array{0?: int' . $operator . 'string}',
                new PhpDocType('array{}', entries: [
                    new PhpDocTupleEntry(
                        new PhpDocType($operator, [new PhpDocType('int'), new PhpDocType('string')]),
                        key: '0',
                        optional: true,
                    ),
                ]),
            ];
        }

        yield 'intersection binds tighter than union' => [
            'int|string&bool',
            new PhpDocType('|', [
                new PhpDocType('int'),
                new PhpDocType('&', [new PhpDocType('string'), new PhpDocType('bool')]),
            ]),
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['int|string', 'int&string', 'list<int|string>'] as $value) {
            foreach ([
                'array<string, '
                    . $value
                    . '>' => 'Field value uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
                'non-empty-array<int, '
                    . $value
                    . '>' => 'Field value uses unsupported map declaration non-empty-array. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            ] as $declaration => $reason) {
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create('array', $declaration, $tag);
                    yield 'map diagnostic ' . $declaration . ' ' . $tag => [
                        $tag === 'param' ? '{}' : '{"value":{"answer":42}}',
                        $class,
                        'Could not create ' . $class . ' from the JSON object: ' . $reason,
                        3,
                    ];
                }
            }
        }
    }
}
