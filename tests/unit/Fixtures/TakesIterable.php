<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class TakesIterable
{
    /**
     * @param iterable<array-key, mixed> $value
     */
    public function __construct(public iterable $value = [])
    {
    }
}
