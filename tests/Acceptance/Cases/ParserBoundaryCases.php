<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function array_map;
use function bin2hex;
use function str_replace;

/** @internal */
final class ParserBoundaryCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public static function objects(): iterable
    {
        foreach (['int', '?int', 'int[]', 'list<string>'] as $otherType) {
            foreach (["\n", "\r\n"] as $newline) {
                $doc =
                    '/**'
                    . $newline
                    . ' * @param '
                    . $otherType
                    . ' $count Number of elements in $value'
                    . $newline
                    . ' * @param list<int> $value The actual collection'
                    . $newline
                    . ' */';
                $class = CollectionDeclarationFixture::create('array', $doc, 'param');
                yield 'parameter mentioned in description ' . $otherType . ' ' . bin2hex($newline) => [
                    new ReflectionClass($class)->newInstance([42]),
                ];
            }
        }

        foreach ([
            '/** @var list<int>*/',
            "/**\n * @var list<int> A collection\n * with a multiline description.\n */",
        ] as $doc) {
            $class = CollectionDeclarationFixture::create('array', $doc, 'var');
            $reflection = new ReflectionClass($class);
            $object = $reflection->newInstance();
            $reflection->getProperty('value')->setValue($object, [42]);
            yield 'property tag formatting ' . $doc => [$object];
        }

        foreach (['', ' ', "\t", "\r\n", "\n * ", "\r\n * "] as $whitespace) {
            foreach ([
                'list<int>' => [42],
                'non-empty-list<int>' => [42],
                'array{int, string}' => [42, 'answer'],
                'array{int, string,}' => [42, 'answer'],
                'array{0: int, 1?: string}' => [42],
                'non-empty-array<string, int>' => ['answer' => 42],
            ] as $declaration => $value) {
                $spaced = str_replace(
                    ['<', '>', '{', '}', ',', ':', '?'],
                    array_map(static fn(string $delimiter): string => $whitespace . $delimiter . $whitespace, [
                        '<',
                        '>',
                        '{',
                        '}',
                        ',',
                        ':',
                        '?',
                    ]),
                    $declaration,
                );
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create('array', $spaced, $tag);
                    $reflection = new ReflectionClass($class);
                    $object = $tag === 'param' ? $reflection->newInstance($value) : $reflection->newInstance();
                    if ($tag === 'var') {
                        $reflection->getProperty('value')->setValue($object, $value);
                    }
                    yield 'parser whitespace ' . bin2hex($whitespace) . ' ' . $declaration . ' ' . $tag => [$object];
                }
            }
        }
    }
}
