<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use InvalidArgumentException;

final class PerformanceGate
{
    /** @return array<string, float> */
    public static function thresholds(string $json): array
    {
        $values = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($values) || $values === []) {
            throw new InvalidArgumentException('Missing performance thresholds');
        }
        $thresholds = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Invalid performance threshold');
            }
            $thresholds[$key] = self::positiveNumber($value);
        }
        return $thresholds;
    }

    private static function positiveNumber(mixed $value): float
    {
        if (!is_int($value) && !is_float($value) || !is_finite((float) $value) || $value <= 0) {
            throw new InvalidArgumentException('Performance thresholds must be finite positive numbers');
        }
        return (float) $value;
    }

    /**
     * @param PerformanceSummary $comparison
     * @param PerformanceSummary $calibration
     * @param array<string, float> $thresholds
     * @return array<string, 'pass'|'regression'|'inconclusive'>
     */
    public static function evaluate(array $comparison, array $calibration, array $thresholds): array
    {
        if ($comparison === []) {
            throw new InvalidArgumentException('Missing performance comparison');
        }
        $verdicts = [];
        foreach ($comparison as $key => $row) {
            $threshold = $thresholds[$key] ?? null;
            $noise = $calibration[$key]['paired_changes_percent'] ?? [];
            $changes = $row['paired_changes_percent'];
            if (
                $threshold === null
                || count($noise) !== 3
                || count($changes) !== 3
                || max(array_map(abs(...), $noise)) > ($threshold / 2)
            ) {
                $verdicts[$key] = 'inconclusive';
                continue;
            }
            $slowPairs = 0;
            foreach ($changes as $change) {
                if ($change > $threshold) {
                    ++$slowPairs;
                }
            }
            $verdicts[$key] = match ($slowPairs) {
                0 => 'pass',
                3 => 'regression',
                default => 'inconclusive',
            };
        }
        return $verdicts;
    }

    /**
     * @param array<string, 'pass'|'regression'|'inconclusive'> $verdicts
     * @param array<string, float> $thresholds
     */
    public static function report(array $verdicts, array $thresholds): string
    {
        $lines = [
            '',
            '## Regression gate',
            '',
            'All three pairs must exceed the percentage limit to confirm a regression. Inconclusive results also block the check; rerun for a clean measurement.',
            '',
            '| Workload | Result | Limit (%) |',
            '|---|---|---:|',
        ];
        foreach ($verdicts as $key => $verdict) {
            $threshold = $thresholds[$key] ?? null;
            $lines[] = sprintf(
                '| %s | %s | %s |',
                str_replace('|', '\\|', $key),
                $verdict,
                $threshold === null ? 'uncalibrated' : (string) $threshold,
            );
        }
        return implode("\n", $lines) . "\n";
    }
}
