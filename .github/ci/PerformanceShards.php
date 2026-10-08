<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

require_once __DIR__ . '/BenchmarkReport.php';

final class PerformanceShards
{
    public static function shardNumber(mixed $value): int
    {
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || (int) $value > 4) {
            throw new RuntimeException('Shard must be an integer from 1 through 4');
        }
        return (int) $value;
    }

    /** @return list<int> */
    public static function assignedIndices(int $count, int|null $shard): array
    {
        $indices = [];
        for ($index = 0; $index < $count; $index++) {
            if ($shard === null || ($index % 4) === ($shard - 1)) {
                $indices[] = $index;
            }
        }
        return $indices;
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        $text = file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException('Missing shard metadata: ' . $path);
        }
        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Shard metadata must be an object');
        }
        $validated = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Shard metadata keys must be strings');
            }
            $validated[$key] = $value;
        }
        return $validated;
    }

    /** @return array{comparisons: non-empty-list<BenchmarkComparison>, status: int, fpm: string} */
    public static function collect(string $directory, string $mode): array
    {
        $directories = glob($directory . '/*', GLOB_ONLYDIR);
        if ($directories === false || count($directories) !== 4) {
            throw new RuntimeException('Expected exactly four shard artifact directories');
        }
        $reference = null;
        $expected = null;
        $seen = [];
        $comparisons = [];
        $status = 0;
        $fpm = '';
        foreach ($directories as $shardDirectory) {
            $metadata = self::json($shardDirectory . '/metadata.json');
            $completion = self::json($shardDirectory . '/complete.json');
            $shard = $metadata['shard'] ?? null;
            if (!is_int($shard) || $shard < 1 || $shard > 4 || isset($seen[$shard])) {
                throw new RuntimeException('Missing, duplicate, or invalid shard number');
            }
            $seen[$shard] = true;
            if (($metadata['opcache'] ?? null) !== $mode || ($metadata['fpm'] ?? null) !== ($shard === 1)) {
                throw new RuntimeException('Shard runtime mode or FPM ownership differs');
            }
            $shared = [];
            foreach (['baseline', 'candidate', 'workloads', 'dependencies_sha256', 'configuration_sha256'] as $key) {
                $value = $metadata[$key] ?? null;
                if (!is_string($value) || $value === '') {
                    throw new RuntimeException('Missing shared shard metadata: ' . $key);
                }
                $shared[$key] = $value;
            }
            $reference ??= $shared;
            $identities = BenchmarkReport::read($shardDirectory . '/discovery.xml')->identities();
            $expected ??= $identities;
            if ($reference !== $shared || $expected !== $identities) {
                throw new RuntimeException(
                    'Shards disagree about revisions, dependencies, configuration, or workloads',
                );
            }
            $shardStatus = $completion['status'] ?? null;
            if ($shardStatus !== 0 && $shardStatus !== 2) {
                throw new RuntimeException('Shard has no completed comparison verdict');
            }
            $status = max($status, $shardStatus);
            $assigned = self::assignedIndices(count($identities), $shard);
            foreach ($assigned as $index) {
                $baseline = BenchmarkReport::read($shardDirectory . '/baseline-' . $index . '.xml');
                $candidate = BenchmarkReport::read($shardDirectory . '/candidate-' . $index . '.xml');
                $comparisons[] = $candidate->compareAgainst($baseline, $identities[$index], $mode);
            }
            if (($completion['indices'] ?? null) !== $assigned) {
                throw new RuntimeException('Shard did not complete exactly its assigned workloads');
            }
            $files = glob($shardDirectory . '/candidate-*.xml');
            if (
                $files === false
                || count(array_filter(
                    $files,
                    static fn(string $path): bool => !str_ends_with($path, '/candidate-workloads.xml'),
                )) !== count($assigned)
            ) {
                throw new RuntimeException('Shard contains unexpected workload measurements');
            }
            if ($shard === 1) {
                self::json($shardDirectory . '/fpm.json');
                $report = file_get_contents($shardDirectory . '/fpm.md');
                if ($report === false || $report === '') {
                    throw new RuntimeException('Missing FPM report');
                }
                $fpm = $report;
            }
        }
        if ($comparisons === [] || count($comparisons) !== count($expected)) {
            throw new RuntimeException('Incomplete workload coverage');
        }
        return ['comparisons' => $comparisons, 'status' => $status, 'fpm' => $fpm];
    }
}
