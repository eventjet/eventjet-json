<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

use const PHP_FLOAT_EPSILON;
use const PHP_FLOAT_MAX;
use const PHP_FLOAT_MIN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class NumericBoundaryRoundTripCases
{
    /**
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
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
    }
}
