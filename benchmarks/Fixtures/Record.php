<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final readonly class Record
{
    public function __construct(
        public int $id,
        public string $name,
        public float $amount,
        public bool $active,
        public string|null $note,
    ) {}
}
