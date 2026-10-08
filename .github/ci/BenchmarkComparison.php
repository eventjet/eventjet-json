<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

final readonly class BenchmarkComparison
{
    public function __construct(
        public BenchmarkWorkload $workload,
        public float $baseline,
        public float $candidate,
    ) {
        if ($baseline <= 0 || $candidate <= 0 || !is_finite($baseline) || !is_finite($candidate)) {
            throw new RuntimeException('Comparison requires positive, finite mode times');
        }
    }
}
