<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use JsonException;
use Throwable;

/**
 * A workload that the CI script for fresh PHP-FPM requests times.
 *
 * @api Used by the PHP-FPM request script in CI.
 */
interface RequestWorkload
{
    /**
     * Does the work that the request times, and returns what verify() checks.
     *
     * @throws Throwable
     */
    public function run(): mixed;

    /**
     * Checks what run() returned, outside the timing.
     *
     * @throws Throwable
     */
    public function verify(mixed $result): void;

    /**
     * The size of the JSON that the workload reads.
     *
     * @throws JsonException
     */
    public function inputBytes(): int;
}
