<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final readonly class RecordBatch
{
    /** @param list<Record> $records */
    public function __construct(
        public array $records,
    ) {}
}
