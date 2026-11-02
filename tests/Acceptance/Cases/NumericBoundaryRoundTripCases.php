<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Random\Engine\Mt19937;
use Random\Randomizer;

use function var_export;

use const PHP_FLOAT_EPSILON;
use const PHP_FLOAT_MAX;
use const PHP_FLOAT_MIN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class NumericBoundaryRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'minimum platform integer' => [new ScalarFields('value', PHP_INT_MIN, 0.0, false)];
        yield 'maximum platform integer' => [new ScalarFields('value', PHP_INT_MAX, 0.0, false)];

        foreach ([
            'positive whole-valued float' => 3.0,
            'negative whole-valued float' => -3.0,
            'minimum positive normal float' => PHP_FLOAT_MIN,
            'minimum positive subnormal float' => PHP_FLOAT_MIN * PHP_FLOAT_EPSILON,
            'maximum finite float' => PHP_FLOAT_MAX,
            'negative maximum finite float' => -PHP_FLOAT_MAX,
            'precision-sensitive decimal' => 0.300_000_000_000_000_04,
            'first float above one' => 1.000_000_000_000_000_2,
            'largest consecutive integer float' => 9_007_199_254_740_991.0,
        ] as $name => $value) {
            yield $name => [new ScalarFields('value', 0, $value, false)];
        }
        yield from self::randomFloats();
    }

    /**
     * Seeded random mantissas and binary exponents, from the subnormal range to the largest finite floats, with both
     * signs. `-0.0` is outside the round-trip domain.
     *
     * @return iterable<string, array{object}>
     */
    private static function randomFloats(): iterable
    {
        $random = new Randomizer(new Mt19937(20_261_102));
        for ($i = 0; $i < 32; $i++) {
            $value = (1.0 + $random->nextFloat()) * (2.0 ** ((float) $random->getInt(-1_074, 1_023)));
            foreach ([$value, -$value] as $signed) {
                yield 'random finite float ' . var_export($signed, return: true) => [
                    new ScalarFields('value', 0, $signed, false),
                ];
            }
        }
    }
}
