<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function count;

/** @internal */
final class RootArrayEnumRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>, string|null, JsonType<list<mixed>>}>
     */
    public static function enums(): iterable
    {
        foreach ([StringBackedStatus::class, IntBackedStatus::class] as $class) {
            $values = [];
            yield from RootArrayInputs::nested($class, $values, $class . ' empty');

            for ($length = 0; $length < 3; ++$length) {
                foreach ($class::cases() as $case) {
                    $values[] = $case;
                    yield from RootArrayInputs::nested($class, $values, $class . ' length ' . count($values));
                }
            }
        }
    }
}
