<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use InvalidArgumentException;
use SimpleXMLElement;

/** Calculations for PHPBench XML, independent of benchmark execution. */
final class PerformanceResults
{
    /** @return PerformanceSamples */
    public static function samples(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml);
            if ($root === false || $root->xpath('//error | //failure') !== []) {
                throw new InvalidArgumentException('Malformed or failed benchmark output');
            }
            $result = [];
            $benchmarks = $root->xpath('//benchmark');
            if ($benchmarks === null) {
                throw new InvalidArgumentException('Cannot read benchmark samples');
            }
            foreach ($benchmarks as $benchmark) {
                foreach ($benchmark->subject as $subject) {
                    foreach ($subject->variant as $variant) {
                        $key =
                            $benchmark['class']
                            . ' / '
                            . $subject['name']
                            . ' / '
                            . $variant->{'parameter-set'}['name'];
                        $revs = self::number($variant, 'revs');
                        $times = $memory = [];
                        foreach ($variant->iteration as $iteration) {
                            $times[] = self::number($iteration, 'time-net') / $revs;
                            $memory[] = (int) self::number($iteration, 'mem-peak');
                        }
                        if ($times === [] || isset($result[$key])) {
                            throw new InvalidArgumentException('Empty or duplicate benchmark samples: ' . $key);
                        }
                        $result[$key] = ['time_us' => $times, 'peak_bytes' => max($memory)];
                    }
                }
            }
            if ($result === []) {
                throw new InvalidArgumentException('No benchmark samples');
            }
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function number(SimpleXMLElement $element, string $attribute): float
    {
        $value = (string) $element[$attribute];
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException('Invalid benchmark attribute: ' . $attribute);
        }
        return (float) $value;
    }

    /**
     * @param list<PerformancePair> $pairs
     * @return PerformanceSummary
     */
    public static function summarize(array $pairs): array
    {
        if ($pairs === [] || $pairs[0][0] === []) {
            throw new InvalidArgumentException('No benchmark pairs');
        }
        $keys = array_keys($pairs[0][0]);
        sort($keys);
        foreach ($pairs as $pair) {
            foreach ($pair as $side) {
                $actual = array_keys($side);
                sort($actual);
                if ($actual !== $keys) {
                    throw new InvalidArgumentException('Benchmark cases differ between runs');
                }
            }
        }
        $result = [];
        foreach ($keys as $key) {
            $baseline = $candidate = $changes = $baseMemory = $candidateMemory = [];
            foreach ($pairs as [$a, $b]) {
                array_push($baseline, ...$a[$key]['time_us']);
                array_push($candidate, ...$b[$key]['time_us']);
                $changes[] = 100 * ((self::median($b[$key]['time_us']) / self::median($a[$key]['time_us'])) - 1);
                $baseMemory[] = $a[$key]['peak_bytes'];
                $candidateMemory[] = $b[$key]['peak_bytes'];
            }
            $result[$key] = [
                'baseline_us' => self::median($baseline),
                'candidate_us' => self::median($candidate),
                'change_percent' => self::median($changes),
                'paired_changes_percent' => $changes,
                'baseline_cv_percent' => self::variation($baseline),
                'candidate_cv_percent' => self::variation($candidate),
                'baseline_peak_bytes' => max($baseMemory),
                'candidate_peak_bytes' => max($candidateMemory),
            ];
        }
        return $result;
    }

    /** @param non-empty-list<float> $values */
    private static function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);
        return (count($values) % 2) === 0 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
    }

    /** @param non-empty-list<float> $values */
    private static function variation(array $values): float
    {
        if (count($values) < 2) {
            throw new InvalidArgumentException('Variation requires at least two samples');
        }
        $mean = array_sum($values) / count($values);
        $squares = array_map(static fn(float $value): float => ($value - $mean) ** 2, $values);
        return (100 * sqrt(array_sum($squares) / (count($values) - 1))) / $mean;
    }

    /**
     * @param PerformanceMetadata $metadata
     * @param PerformanceSummary $comparison
     * @param PerformanceSummary $calibration
     */
    public static function report(array $metadata, array $comparison, array $calibration): string
    {
        $lines = [
            '## Performance comparison',
            '',
            "Baseline: `{$metadata['baseline']}`. Candidate: `{$metadata['candidate']}`.",
            '',
            'Reporting only: no regression threshold has been calibrated. Positive changes mean slower execution.',
            '',
            'Both versions use the baseline workloads and the same installed dependencies on the same runner. '
                . 'Three independent pairs alternate A/B and B/A order, with five iterations per invocation. '
                . 'Cold/warm settings come from the frozen benchmark suite; PCOV, Xdebug coverage, OPcache, and JIT are disabled.',
            '',
            'The unchanged-code A/A comparison estimates noise for this run, not a statistical confidence interval. '
                . 'Archive multiple runs before defining a required regression gate. Memory is whole benchmark-process peak, not decoder-only allocation.',
            '',
            '| Workload | Base µs/decode | Candidate µs/decode | Paired change | Pair range | A/A max absolute change | CV base / candidate | Peak MiB base / candidate |',
            '|---|---:|---:|---:|---:|---:|---:|---:|',
        ];
        foreach ($comparison as $key => $row) {
            $changes = $row['paired_changes_percent'];
            $lines[] = sprintf(
                '| %s | %.2f | %.2f | %+.1f%% | %+.1f%% to %+.1f%% | %.1f%% | %.1f%% / %.1f%% | %.2f / %.2f |',
                str_replace('|', '\\|', $key),
                $row['baseline_us'],
                $row['candidate_us'],
                $row['change_percent'],
                min($changes),
                max($changes),
                max(array_map(abs(...), $calibration[$key]['paired_changes_percent'])),
                $row['baseline_cv_percent'],
                $row['candidate_cv_percent'],
                $row['baseline_peak_bytes'] / (2 ** 20),
                $row['candidate_peak_bytes'] / (2 ** 20),
            );
        }
        if ($metadata['workloads_changed']) {
            $lines[] = '';
            $lines[] = 'Benchmark or fixture files changed in this candidate. Those changes are excluded from this comparison; the candidate suite is also run separately and retained as `candidate-workloads.xml`.';
        }
        return implode("\n", $lines) . "\n";
    }
}
