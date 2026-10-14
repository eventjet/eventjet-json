<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

final class Statistics
{
    /** @param non-empty-list<float> $values */
    public static function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
