<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IndexedTupleFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function count;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class IndexedTupleRoundTripCases
{
    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        foreach ([PHP_INT_MIN, 0, PHP_INT_MAX] as $integer) {
            foreach (['', '42', 'Grüße, 世界'] as $string) {
                $person = new Person($string, 'Tuple');

                foreach ([[$person, $string], [$person, $string, $integer]] as $value) {
                    foreach (self::optionalValues($integer, $string) as $publicValue) {
                        $tuple = new IndexedTupleFields($value);
                        $tuple->publicValue = $publicValue;
                        $tuple->required = [$integer, $string];
                        $tuple->refined = [$integer, $string, 5];
                        yield 'indexed tuple '
                            . $integer
                            . '/'
                            . $string
                            . '/'
                            . count($value)
                            . '/'
                            . count($publicValue) => [$tuple];
                    }
                }
            }
        }
    }

    /** @return iterable<array{0?: int, 1?: string, 2?: float, 3?: bool, 4?: StringBackedStatus, 5?: IntBackedStatus, 6?: Person}> */
    private static function optionalValues(int $integer, string $string): iterable
    {
        yield [];
        yield [$integer];
        yield [$integer, $string];
        yield [$integer, $string, 3.0];
        yield [$integer, $string, -3.25, false];
        yield [$integer, $string, 0.0, true, StringBackedStatus::Ready];
        yield [$integer, $string, 3.0, false, StringBackedStatus::Pending, IntBackedStatus::Ready];
        yield [
            $integer,
            $string,
            -3.25,
            true,
            StringBackedStatus::Ready,
            IntBackedStatus::Pending,
            new Person($string, 'Public'),
        ];
    }
}
