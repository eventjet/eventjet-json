<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

use function str_repeat;
use function strlen;
use function substr;

/** @internal */
final class ParserMalformedCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from ParserCompoundCases::errors();

        $class = CollectionDeclarationFixture::create('array', 'list<int> extra', 'param');
        yield 'constructor type must end before parameter name' => [
            '{}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @param with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            3,
        ];

        foreach (self::declarations() as $label => [$native, $declaration]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield $label . ' ' . $tag => [
                    $tag === 'param' ? '{}' : '{"value":[42]}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value has a missing or unrecognized collection declaration. Use @'
                        . $tag
                        . ' with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
                    3,
                ];
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    private static function declarations(): iterable
    {
        foreach ([
            'list<int>',
            'non-empty-list<string>',
            'array{int, string}',
            'array{0: int, 1?: string}',
            'non-empty-array<string, int>',
        ] as $valid) {
            foreach (['>', '<', ',', '?', '[]', '|int', '&int', 'garbage'] as $suffix) {
                yield 'parser suffix ' . $valid . $suffix => ['array', $valid . $suffix];
            }
            for ($length = 1; $length < strlen($valid); $length++) {
                yield 'parser truncated ' . $valid . ' at ' . $length => [
                    'array',
                    substr($valid, offset: 0, length: $length),
                ];
            }
        }
        foreach ([
            'non-empty-array<string, int|string>',
            'non-empty-array<string, int&string>',
            'list<>',
            'list<int,>',
            'list<int,,string>',
            'list<int{string}>',
            'list<array{}>',
            'list<non-empty-array<string, list<int>>>',
            'array{0?: int, 1: string}',
            'array{999999999999999999999999: int}',
            'array{0?:}',
            'array{: int}',
            'array{0??: int}',
            'array{int<>}',
            'array{int<min, max, 0>}',
            'array{int<max, min>}',
            'array{int<min, list<int>>}',
            'list<\\>',
            'list<\\\\int>',
            'list<int\\>',
            'list<int<min, max>>junk',
            'Unknown<string, int>',
            'non-empty-array<string, int, bool>',
            'ArrayObject<string, int>',
            'list<int>   |string',
            "list<\n * int\n * > |string",
        ] as $declaration) {
            yield 'parser malformed or unsupported ' . $declaration => ['array', $declaration];
        }
        foreach ([2, 63, 64, 65] as $depth) {
            yield 'parser nesting ' . $depth => [
                'array',
                str_repeat('list<', $depth) . 'int' . str_repeat('>', $depth),
            ];
        }
    }
}
