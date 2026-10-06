<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class NonZeroIntegerErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (CollectionUnionCases::shapes('non-zero-int') as $shape => [$native, $declaration]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                $map = $shape === 'map' || $shape === 'non-empty-map' || $shape === 'ArrayObject';
                foreach ([
                    '"1"' => 'string',
                    '1.0' => 'float',
                    'true' => 'bool',
                    'null' => 'null',
                    '[]' => 'array',
                    '{}' => 'stdClass',
                ] as $value => $actual) {
                    $json = '{"value":' . ($map ? '{"key":' . $value . '}' : '[' . $value . ']') . '}';
                    $path = $map ? 'value[key]' : 'value[0]';
                    yield 'non-zero-int mismatch ' . $declaration . $tag . $value => [
                        $json,
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field '
                            . $path
                            . ' must be of type int, '
                            . $actual
                            . ' given.',
                        3,
                    ];
                }
            }
        }
    }
}
