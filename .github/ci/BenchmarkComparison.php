<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

final readonly class BenchmarkComparison
{
    /**
     * The runner slows down for seconds at a time. Alternating short rounds between the revisions, in ABBA order,
     * spreads those slow stretches over both sides of the comparison.
     */
    public const ROUNDS = 20;
    public const ITERATIONS = 2;
    public const LIMIT_PERCENT = 3.0;

    public function __construct(
        public BenchmarkWorkload $workload,
        public float $baseline,
        public float $candidate,
    ) {
        if ($baseline <= 0 || $candidate <= 0 || !is_finite($baseline) || !is_finite($candidate)) {
            throw new RuntimeException('Comparison requires positive, finite median times');
        }
    }

    /** @return list<'baseline'|'candidate'> */
    public static function order(int $round): array
    {
        return $round % 2 === 0 ? ['baseline', 'candidate'] : ['candidate', 'baseline'];
    }

    public function change(): float
    {
        return ($this->candidate / $this->baseline - 1) * 100;
    }

    public function slower(): bool
    {
        return $this->candidate > $this->baseline * (1 + self::LIMIT_PERCENT / 100);
    }

    public function faster(): bool
    {
        return $this->candidate < $this->baseline * (1 - self::LIMIT_PERCENT / 100);
    }
}
