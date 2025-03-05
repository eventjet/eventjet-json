<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Parser;

use Eventjet\Json\Parser\SyntaxError;
use Eventjet\Json\Parser\Token;
use Eventjet\Json\Parser\Tokenizer;
use Eventjet\Json\Parser\TokenLocation;
use PHPUnit\Framework\TestCase;

use function array_map;
use function implode;
use function json_decode;
use function json_encode;

final class TokenizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidJsonStrings(): iterable
    {
        $cases = [
            'null',
            'true',
            'false',
            '""',
            '"foo"',
            '0',
            '42',
            '-42',
            '3.14',
            '-3.14',
            '[]',
            '{}',
            '["foo"]',
            '["foo", "bar"]',
            '{"foo": "bar"}',
            '{"foo": "bar", "baz": "qux"}',
            '{"a": null, "b": true, "c": false, "d": "", "e": 0, "f": 42, "g": -42, "h": 3.14, "i": -3.14}',
            <<<JSON
                "a\\\\b\\nc"
                JSON,
            ' { "foo" : [ "bar", 42 ] , "bar" : null }',
            '10e3',
            '10E+5',
        ];
        foreach ($cases as $case) {
            yield $case => [$case];
        }
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function provideInvalidJsonStrings(): iterable
    {
        $cases = [
            'unexpected' => [1, 1],
            '"unterminated string' => [1, 21],
            '"unterminated string\\' => [1, 22],
            "{\n  missingstartquote\" => \"\" }" => [2, 3],
        ];
        foreach ($cases as $case => [$line, $column]) {
            yield $case => [$case, $line, $column];
        }
    }

    /**
     * @dataProvider provideValidJsonStrings
     */
    public function testMatchesNative(string $json): void
    {
        $expected = json_encode(json_decode($json));
        $actual = implode(
            '',
            array_map(
                static fn(TokenLocation $token) => Token::print($token->token),
                Tokenizer::tokenize($json),
            ),
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideInvalidJsonStrings
     */
    public function testSyntaxError(string $json, int $line, int $column): void
    {
        try {
            Tokenizer::tokenize($json);
            self::fail('Expected a SyntaxError');
        } catch (SyntaxError $e) {
            self::assertSame($line, $e->location->line);
            self::assertSame($column, $e->location->column);
        }
    }
}
