<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar;

/** @internal */
final class HydrationCheck
{
    /**
     * @param iterable<array-key, object> $values
     * @param class-string $class
     */
    public static function allInstancesAre(iterable $values, string $class): bool
    {
        foreach ($values as $value) {
            if (!$value instanceof $class) {
                return false;
            }
        }

        return true;
    }
}
