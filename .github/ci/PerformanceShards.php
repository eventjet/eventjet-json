<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

require_once __DIR__ . '/BenchmarkReport.php';
require_once __DIR__ . '/FpmMeasurements.php';

final class PerformanceShards
{
    public const SHARDS = 8;

    public static function shardNumber(mixed $value): int
    {
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || (int) $value > self::SHARDS) {
            throw new RuntimeException('Shard must be an integer from 1 through ' . self::SHARDS);
        }
        return (int) $value;
    }

    /** @return list<int> */
    public static function assignedIndices(int $count, int|null $shard): array
    {
        $indices = [];
        for ($index = 0; $index < $count; $index++) {
            if ($shard === null || ($index % self::SHARDS) === ($shard - 1)) {
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

    /** @return array{comparisons: non-empty-list<BenchmarkComparison>, fpm: non-empty-list<FpmComparison>} */
    public static function collect(string $directory, string $mode): array
    {
        $directories = glob($directory . '/*', GLOB_ONLYDIR);
        if ($directories === false || count($directories) !== self::SHARDS) {
            throw new RuntimeException('Expected exactly one artifact directory per shard');
        }
        $reference = null;
        $expected = null;
        $seen = [];
        $comparisons = [];
        $fpm = null;
        foreach ($directories as $shardDirectory) {
            $metadata = self::json($shardDirectory . '/metadata.json');
            $completion = self::json($shardDirectory . '/complete.json');
            $shard = $metadata['shard'] ?? null;
            if (!is_int($shard) || $shard < 1 || $shard > self::SHARDS || isset($seen[$shard])) {
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
            $assigned = self::assignedIndices(count($identities), $shard);
            foreach ($assigned as $index) {
                $rounds = ['baseline' => [], 'candidate' => []];
                foreach ($rounds as $version => $reports) {
                    for ($round = 0; $round < BenchmarkComparison::ROUNDS; ++$round) {
                        $rounds[$version][] = BenchmarkReport::read(
                            $shardDirectory . '/' . $version . '-' . $index . '-' . $round . '.xml',
                        );
                    }
                }
                $comparisons[] = BenchmarkReport::compare(
                    $rounds['baseline'],
                    $rounds['candidate'],
                    $identities[$index],
                    $mode,
                );
            }
            if (($completion['indices'] ?? null) !== $assigned) {
                throw new RuntimeException('Shard did not complete exactly its assigned workloads');
            }
            $files = glob($shardDirectory . '/{baseline,candidate}-[0-9]*.xml', GLOB_BRACE);
            if ($files === false || count($files) !== 2 * BenchmarkComparison::ROUNDS * count($assigned)) {
                throw new RuntimeException('Shard contains unexpected workload measurements');
            }
            if ($shard === 1) {
                $samples = self::json($shardDirectory . '/fpm.json');
                $fpm = FpmMeasurements::compare($samples['baseline'] ?? null, $samples['candidate'] ?? null, $mode === 'on');
            }
        }
        if ($comparisons === [] || count($comparisons) !== count($expected)) {
            throw new RuntimeException('Incomplete workload coverage');
        }
        if ($fpm === null) {
            throw new RuntimeException('Missing PHP-FPM measurements');
        }
        return ['comparisons' => $comparisons, 'fpm' => $fpm];
    }
}
