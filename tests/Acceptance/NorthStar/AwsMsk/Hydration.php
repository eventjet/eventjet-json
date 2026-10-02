<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk;

use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;

/** @internal */
final class Hydration
{
    public static function isComplete(object $value): bool
    {
        if (!$value instanceof MskEvent) {
            return false;
        }

        foreach ($value->records as $records) {
            $recordsAreHydrated = HydrationCheck::allInstancesAre($records, KafkaRecord::class);

            if (!$recordsAreHydrated) {
                return false;
            }
        }

        return true;
    }
}
