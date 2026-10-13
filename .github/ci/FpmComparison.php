<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

final readonly class FpmComparison
{
    public function __construct(
        public string $workload,
        public float $baseline,
        public float $candidate,
        public int $baselinePeakBytes,
        public int $candidatePeakBytes,
    ) {
        if ($baseline <= 0 || $candidate <= 0 || !is_finite($baseline) || !is_finite($candidate)) {
            throw new RuntimeException('FPM comparison requires positive, finite median times');
        }
    }

    public function change(): float
    {
        return ($this->candidate / $this->baseline - 1) * 100;
    }

    public function slower(): bool
    {
        return $this->candidate > $this->baseline * (1 + FpmMeasurements::LIMIT_PERCENT / 100);
    }

    public function faster(): bool
    {
        return $this->candidate < $this->baseline * (1 - FpmMeasurements::LIMIT_PERCENT / 100);
    }
}
