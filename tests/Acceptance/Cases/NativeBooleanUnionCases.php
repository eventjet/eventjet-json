<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;

use function array_flip;
use function array_intersect_key;
use function array_values;
use function implode;
use function var_export;

/**
 * Native types that combine `true` or `false` with any of `string`, `int`, and `float`, and optionally `null`.
 *
 * @internal
 */
final class NativeBooleanUnionCases
{
    private const array SAMPLES = ['string' => 'answer', 'int' => 42, 'float' => 1.5];

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (self::declarations() as $native => [, , $values]) {
            foreach (['param', 'var'] as $tag) {
                foreach ($values as $value) {
                    yield 'native ' . $native . ' ' . $tag . ' preserves ' . var_export($value, return: true) => [
                        CollectionDeclarationFixture::object($native, '', $tag, $value),
                    ];
                }
            }
        }
    }

    /** @return iterable<string, array{string, class-string, string, DecodeErrorKind}> */
    public static function errors(): iterable
    {
        foreach (self::declarations() as $native => [$type, $boolean]) {
            $class = CollectionDeclarationFixture::create($native, '', 'param');
            yield 'native ' . $native . ' rejects ' . var_export(!$boolean, return: true) => [
                '{"value":' . ($boolean ? 'false' : 'true') . '}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value must be of type '
                    . $type
                    . ', bool given.',
                DecodeErrorKind::InvalidValue,
            ];
        }
    }

    /**
     * Returns each declaration with the type that errors report, its Boolean member, and a value of each member. Members
     * follow the order in which PHP spells a union, so that errors report the union as it is declared here.
     *
     * @return iterable<string, array{string, bool, non-empty-list<string|int|float|bool|null>}>
     */
    private static function declarations(): iterable
    {
        foreach ([
            [],
            ['string'],
            ['int'],
            ['float'],
            ['string', 'int'],
            ['string', 'float'],
            ['int', 'float'],
            ['string', 'int', 'float'],
        ] as $scalars) {
            $values = array_values(array_intersect_key(self::SAMPLES, array_flip($scalars)));
            foreach ([true, false] as $boolean) {
                $type = implode('|', [...$scalars, $boolean ? 'true' : 'false']);
                yield $type => [$type, $boolean, [...$values, $boolean]];
                yield $type . '|null' => [$type . '|null', $boolean, [...$values, $boolean, null]];
            }
        }
        yield '?true' => ['true|null', true, [true, null]];
        yield '?false' => ['false|null', false, [false, null]];
    }
}
