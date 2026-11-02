<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Performance\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final class PlainProperties
{
    public string $ref = 'example';
    public int $count = 42;
    public bool $ready = true;
}
