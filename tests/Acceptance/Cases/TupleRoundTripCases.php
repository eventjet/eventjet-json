<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\TupleFields;

use function implode;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class TupleRoundTripCases
{
    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        foreach ([PHP_INT_MIN, -1, 0, PHP_INT_MAX] as $integer) {
            foreach (['', '42', 'Grüße, 世界'] as $string) {
                foreach ([0.0, 3.0, -3.25] as $float) {
                    foreach ([false, true] as $boolean) {
                        $tuple = new TupleFields([
                            $integer,
                            $string,
                            $float,
                            $boolean,
                            $boolean ? StringBackedStatus::Ready : StringBackedStatus::Pending,
                            $boolean ? IntBackedStatus::Ready : IntBackedStatus::Pending,
                            new Person($string, 'Tuple'),
                        ]);
                        $tuple->publicValue = [
                            $integer,
                            $string,
                            $float,
                            !$boolean,
                            StringBackedStatus::Ready,
                            IntBackedStatus::Pending,
                            new Person('Public', $string),
                        ];
                        $tuple->single = [$boolean ? -5 : 5];

                        yield 'tuple ' . implode('/', [$integer, $string, $float, (int) $boolean]) => [$tuple];
                    }
                }
            }
        }
    }
}
