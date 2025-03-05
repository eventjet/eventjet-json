<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Parser;

use Eventjet\Json\Parser\Parser;
use Eventjet\Json\Parser\SyntaxError;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidJsonStrings(): iterable
    {
        $cases = [
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
        ];
        foreach ($cases as $case) {
            yield $case => [$case];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidJsonStrings(): iterable
    {
        $cases = [
            '',
            '{',
            '}',
            '{"foo"',
            '{"foo":',
            '{"foo": "bar"',
            '{"foo": "bar",',
            '{"foo": "bar" "baz": "qux"}',
            '{: "foo"}',
            '{null: "foo"}',
            '{true: "foo"}',
            '{false: "foo"}',
            '{0: "foo"}',
            '{42: "foo"}',
            '{-42: "foo"}',
            '{3.14: "foo"}',
            '{-3.14: "foo"}',
            '{[]: "foo"}',
            '[',
            ']',
            '["foo"',
            '["foo",',
            '["foo" "bar"]',
        ];
        foreach ($cases as $case) {
            yield $case => [$case];
        }
    }

    /**
     * @dataProvider provideValidJsonStrings
     */
    public function testParityWithNativeFunction(string $json): void
    {
        $actual = Parser::parse($json);
        /** @var mixed $expected */
        $expected = json_decode($json);

        self::assertSame(json_encode($expected), json_encode($actual));
    }

    /**
     * @dataProvider provideInvalidJsonStrings
     */
    public function testInvalidJsonThrowsException(string $json): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parse($json);
    }
}
