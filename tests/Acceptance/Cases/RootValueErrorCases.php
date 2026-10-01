<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use JsonException;

use function array_slice;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class RootValueErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function unexpectedRootValues(): iterable
    {
        foreach ([
            ['null', 'null'],
            ['true', 'bool'],
            ['false', 'bool'],
            ['0', 'int'],
            ['42', 'int'],
            ['3.14', 'float'],
            ['"Ada"', 'string'],
            ['""', 'string'],
        ] as [$json, $type]) {
            yield 'JSON scalar: ' . $json => [
                $json,
                Person::class,
                'Expected the JSON root to be an object, got ' . $type . '.',
                2,
            ];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function arrayRootValues(): iterable
    {
        $arguments = ['Ada', 'Lovelace', 'Byron', 36];

        for ($length = 0; $length <= 4; $length++) {
            yield 'JSON array with ' . $length . ' constructor arguments' => [
                json_encode(array_slice($arguments, offset: 0, length: $length), JSON_THROW_ON_ERROR),
                Person::class,
                'Expected the JSON root to be an object, got array.',
                2,
            ];
        }

        foreach (['[null]', '[true]', '[42]', '[3.14]', '[[]]', '[{}]', '[["Ada","Lovelace"]]'] as $json) {
            yield 'JSON array: ' . $json => [
                $json,
                Person::class,
                'Expected the JSON root to be an object, got array.',
                2,
            ];
        }

        foreach ([
            'space' => ' ',
            'tab' => "\t",
            'carriage return' => "\r",
            'line feed' => "\n",
            'all' => " \t\r\n",
        ] as $name => $whitespace) {
            yield 'array root whitespace: ' . $name => [
                $whitespace . '["Ada","Lovelace"]' . $whitespace,
                Person::class,
                'Expected the JSON root to be an object, got array.',
                2,
            ];
        }
    }
}
