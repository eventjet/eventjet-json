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
        public float $ratio,
        public int $baselinePeakBytes,
        public int $candidatePeakBytes,
    ) {
        foreach ([$baseline, $candidate, $ratio] as $value) {
            if ($value <= 0 || !is_finite($value)) {
                throw new RuntimeException('FPM comparison requires positive, finite medians');
            }
        }
    }

    public function change(): float
    {
        return ($this->ratio - 1) * 100;
    }

    public function slower(): bool
    {
        return $this->ratio > 1 + FpmMeasurements::LIMIT_PERCENT / 100;
    }

    public function faster(): bool
    {
        return $this->ratio < 1 - FpmMeasurements::LIMIT_PERCENT / 100;
    }
}
