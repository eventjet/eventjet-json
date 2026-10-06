<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class NonEmptyMapErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create('array', 'non-empty-map<string, int>', $tag);
            foreach ([
                '{}' => ' uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.',
                '[]' => ' must be of type JSON object, array given.',
                '[1]' => ' must be of type JSON object, array given.',
                '{"0":1}' => ' has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
                '{"valid":1,"invalid":"1"}' => '[invalid] must be of type int, string given.',
            ] as $value => $reason) {
                yield 'non-empty-map shape ' . $tag . $value => [
                    '{"value":' . $value . '}',
                    $class,
                    'Could not create ' . $class . ' from the JSON object: Field value' . $reason,
                    3,
                ];
            }
            foreach (['int', 'string|int', 'string<int>'] as $key) {
                $class = CollectionDeclarationFixture::create('array', 'non-empty-map<' . $key . ', int>', $tag);
                foreach (['{}', '{"value":{"valid":1}}'] as $json) {
                    yield 'non-empty-map key declaration ' . $key . $tag . $json => [
                        $json,
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field value uses unsupported map declaration non-empty-map. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
                        3,
                    ];
                }
            }
        }
    }
}
