<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;

use function str_repeat;

/** @internal */
final class DecodeErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function unexpectedRootValues(): iterable
    {
        yield 'JSON scalar' => ['null', Person::class, 'Expected the JSON root to be an object, got null.', 2];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function constructionFailures(): iterable
    {
        yield 'missing constructor arguments' => [
            '{}',
            Person::class,
            'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person from the JSON object: Too few arguments to function Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person::__construct(), 0 passed and at least 2 expected',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function malformedDocuments(): iterable
    {
        yield 'malformed JSON' => ['{', Person::class, 'Invalid JSON: Syntax error', 1];

        foreach ([
            'empty document' => '',
            'missing member value' => '{"firstName":}',
            'trailing comma' => '{"firstName":"Ada",}',
            'unquoted member name' => '{firstName:"Ada"}',
            'single-quoted string' => "{\"firstName\":'Ada'}",
            'invalid escape' => '{"firstName":"\\x"}',
        ] as $name => $json) {
            yield $name => [$json, Person::class, 'Invalid JSON: Syntax error', 1];
        }

        yield 'unterminated string' => [
            '{"firstName":"Ada}',
            Person::class,
            'Invalid JSON: Control character error, possibly incorrectly encoded',
            1,
        ];
        yield 'unescaped control character' => [
            "{\"firstName\":\"Ada\n\"}",
            Person::class,
            'Invalid JSON: Control character error, possibly incorrectly encoded',
            1,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function documentsWithTrailingContent(): iterable
    {
        foreach (['{}', '[]', 'null', 'true', '42', '"text"'] as $document) {
            foreach ([' {}', ' []', ' null', ' true', ' 42', ' garbage'] as $suffix) {
                yield 'trailing content: ' . $document . $suffix => [
                    $document . $suffix,
                    Person::class,
                    'Invalid JSON: Syntax error',
                    1,
                ];
            }
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function invalidUtf8Documents(): iterable
    {
        foreach ([
            'lone continuation byte' => "\x80",
            'truncated sequence' => "\xc2",
            'overlong sequence' => "\xc0\xaf",
            'UTF-16 surrogate' => "\xed\xa0\x80",
            'beyond Unicode range' => "\xf4\x90\x80\x80",
        ] as $name => $bytes) {
            yield 'invalid UTF-8: ' . $name => [
                '{"firstName":"' . $bytes . '","lastName":"Lovelace"}',
                Person::class,
                'Invalid JSON: Malformed UTF-8 characters, possibly incorrectly encoded',
                1,
            ];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function deeplyNestedDocuments(): iterable
    {
        foreach (['array' => ['[', ']'], 'object' => ['{"nested":', '}']] as $name => [$opening, $closing]) {
            foreach ([512, 513, 1024] as $depth) {
                yield $name . ' nesting depth: ' . $depth => [
                    str_repeat($opening, $depth) . '0' . str_repeat($closing, $depth),
                    Person::class,
                    'Invalid JSON: Maximum stack depth exceeded',
                    1,
                ];
            }
        }
    }
}
