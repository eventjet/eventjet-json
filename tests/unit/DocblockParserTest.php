<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\DocblockParser;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocblockParser::class)]
final class DocblockParserTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string, string}>
     */
    public static function paramTypeProvider(): Generator
    {
        yield 'simple list' => [
            '/** @param list<string> $items */',
            'items',
            'list<string>',
        ];

        yield 'nested list' => [
            '/** @param list<list<int>> $matrix */',
            'matrix',
            'list<list<int>>',
        ];

        yield 'union in list' => [
            '/** @param list<string|int> $values */',
            'values',
            'list<string|int>',
        ];

        yield 'union with list' => [
            '/** @param string|list<string> $data */',
            'data',
            'string|list<string>',
        ];

        yield 'list of class' => [
            '/** @param list<Foo\Bar> $items */',
            'items',
            'list<Foo\Bar>',
        ];

        yield 'multiline docblock' => [
            <<<'DOC'
                /**
                 * Process items.
                 *
                 * @param list<string> $items The items to process
                 * @return void
                 */
                DOC,
            'items',
            'list<string>',
        ];

        yield 'multiple params' => [
            <<<'DOC'
                /**
                 * @param string $name
                 * @param list<int> $numbers
                 * @param bool $flag
                 */
                DOC,
            'numbers',
            'list<int>',
        ];

        yield 'param with description' => [
            '/** @param list<string> $items The list of items */',
            'items',
            'list<string>',
        ];

        yield 'param at end of line' => [
            "/** @param list<string> \$items\n */",
            'items',
            'list<string>',
        ];
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function missingParamProvider(): Generator
    {
        yield 'empty docblock' => ['/** */', 'items'];
        yield 'no params' => ['/** @return void */', 'items'];
        yield 'different param' => ['/** @param list<string> $other */', 'items'];
        yield 'no docblock' => ['', 'items'];
    }

    #[DataProvider('paramTypeProvider')]
    public function testExtractsParamType(string $docblock, string $paramName, string $expected): void
    {
        $parser = new DocblockParser();
        $result = $parser->getParamType($docblock, $paramName);

        self::assertSame($expected, $result);
    }

    #[DataProvider('missingParamProvider')]
    public function testReturnsNullWhenParamNotFound(string $docblock, string $paramName): void
    {
        $parser = new DocblockParser();
        $result = $parser->getParamType($docblock, $paramName);

        self::assertNull($result);
    }
}
