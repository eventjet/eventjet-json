<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final readonly class ShapeRecord
{
    /**
     * @param array{int, string, bool} $header
     * @param list<array{float, float}> $points
     * @param array{string, list<int>} $series
     */
    public function __construct(
        public array $header,
        public array $points,
        public array $series,
    ) {}
}
