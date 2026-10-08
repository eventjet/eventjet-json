<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;
use SimpleXMLElement;

final class PerformanceShards
{
    public static function shardNumber(mixed $value): int
    {
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || (int) $value > 4) {
            throw new RuntimeException('Shard must be an integer from 1 through 4');
        }
        return (int) $value;
    }

    /** @return non-empty-list<string> */
    public static function identities(string $path): array
    {
        $xml = self::xml($path);
        $identities = [];
        foreach ($xml->suite as $suite) {
            foreach ($suite->benchmark as $benchmark) {
                foreach ($benchmark->subject as $subject) {
                    foreach ($subject->variant as $variant) {
                        $identities[] =
                            (string) $benchmark['class']
                            . '::'
                            . (string) $subject['name']
                            . '['
                            . (string) $variant->{'parameter-set'}['name']
                            . ']';
                    }
                }
            }
        }
        if ($identities === [] || count(array_unique($identities)) !== count($identities)) {
            throw new RuntimeException('Workloads must be nonempty and unique');
        }
        return $identities;
    }

    public static function xml(string $path): SimpleXMLElement
    {
        if (!is_file($path)) {
            throw new RuntimeException('Missing measurement: ' . $path);
        }
        $xml = simplexml_load_file($path);
        if ($xml === false) {
            throw new RuntimeException('Cannot read measurement: ' . $path);
        }
        return $xml;
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

    /** @return array{paths: non-empty-list<string>, status: int, fpm: string} */
    public static function collect(string $directory, string $mode): array
    {
        $directories = glob($directory . '/*', GLOB_ONLYDIR);
        if ($directories === false || count($directories) !== 4) {
            throw new RuntimeException('Expected exactly four shard artifact directories');
        }
        $reference = null;
        $expected = null;
        $seen = [];
        $paths = [];
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
            $identities = self::identities($shardDirectory . '/discovery.xml');
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
            $assigned = [];
            foreach ($identities as $index => $identity) {
                if (($index % 4) !== ($shard - 1)) {
                    continue;
                }
                $assigned[] = $index;
                $baseline = $shardDirectory . '/baseline-' . $index . '.xml';
                $candidate = $shardDirectory . '/candidate-' . $index . '.xml';
                foreach ([$baseline, $candidate] as $path) {
                    if (self::identities($path) !== [$identity]) {
                        throw new RuntimeException('Measured workload differs from the assigned workload');
                    }
                    $xml = self::xml($path);
                    $iterations = $xml->xpath('//variant/iteration');
                    $opcache = $xml->xpath('//env/opcache/value[@name="enabled"]');
                    if (
                        $iterations === null
                        || count($iterations) !== 20
                        || $opcache === null
                        || count($opcache) !== 1
                        || !in_array((string) $opcache[0], $mode === 'on' ? ['1'] : ['', '0'], true)
                    ) {
                        throw new RuntimeException('Measurement sampling or OPcache state differs');
                    }
                }
                $baseVariant = self::xml($baseline)->xpath('//variant');
                $candidateVariant = self::xml($candidate)->xpath('//variant');
                if (
                    $baseVariant === null
                    || $candidateVariant === null
                    || count($baseVariant) !== 1
                    || count($candidateVariant) !== 1
                    || count($candidateVariant[0]->{'baseline-stats'}) !== 1
                    || (string) $baseVariant[0]->stats['mode']
                        !== (string) $candidateVariant[0]->{'baseline-stats'}['mode']
                    || (string) $baseVariant[0]['revs'] !== (string) $candidateVariant[0]['revs']
                    || (string) $baseVariant[0]['warmup'] !== (string) $candidateVariant[0]['warmup']
                ) {
                    throw new RuntimeException('Candidate comparison does not match its baseline measurement');
                }
                $paths[] = $candidate;
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
        if ($paths === [] || count($paths) !== count($expected)) {
            throw new RuntimeException('Incomplete workload coverage');
        }
        return ['paths' => $paths, 'status' => $status, 'fpm' => $fpm];
    }
}
