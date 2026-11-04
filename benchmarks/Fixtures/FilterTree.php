<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\AndFilter;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Equals;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\OrFilter;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Query;

/** @internal */
final class FilterTree
{
    /** Returns a query whose filters alternate between `and` and `or` down to `equals` leaves. */
    public static function query(int $depth): Query
    {
        return new Query(self::filter($depth));
    }

    private static function filter(int $depth): AndFilter|OrFilter|Equals
    {
        if ($depth === 0) {
            return new Equals('equals', 'status', 'ready');
        }
        $operands = [
            self::filter($depth - 1),
            new Equals('equals', 'depth', (string) $depth),
            self::filter($depth - 1),
        ];
        return ($depth % 2) === 0 ? new AndFilter('and', $operands) : new OrFilter('or', $operands);
    }
}
