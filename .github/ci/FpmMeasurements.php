<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

require_once __DIR__ . '/FpmComparison.php';

/** Measures fresh requests in an isolated PHP-FPM pool with warm bytecode. */
final class FpmMeasurements
{
    public const LIMIT_PERCENT = 2.0;

    /** Production servers enable OPcache; without it, every request recompiles the library and mostly measures code size. */
    public static function blocks(bool $opcache): bool
    {
        return $opcache;
    }

    /** Without OPcache, every request recompiles the library and the blocking sample size would exceed the job limit. */
    public static function rounds(bool $opcache): int
    {
        return $opcache ? 40 : 10;
    }

    public static function requests(bool $opcache): int
    {
        return $opcache ? 20 : 10;
    }

    /** @return non-empty-list<string> */
    public static function names(string $workspace): array
    {
        // Discover names with the same frozen fixtures and autoloader used by FPM.
        $names = json_decode(
            BenchmarkCommand::run([
                PHP_BINARY,
                '-r',
                'require "vendor/autoload.php"; echo json_encode(Eventjet\\Json\\Benchmark\\DocumentWorkloads::names(), JSON_THROW_ON_ERROR);',
            ], $workspace),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        if (!is_array($names) || $names === []) {
            throw new RuntimeException('No PHP-FPM document workloads.');
        }
        $validated = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                throw new RuntimeException('Invalid document name.');
            }
            $validated[] = $name;
        }
        return $validated;
    }

    /**
     * Request times shift between pool restarts by more than they vary within one pool. Alternating many short pools
     * between the revisions, in ABBA order, averages those shifts and runner drift out of the comparison.
     *
     * @param callable(string, int): array<string, array{time_us: non-empty-list<float>, peak_bytes: int}> $round
     * @return array{
     *     baseline: array<string, array{time_us: list<float>, peak_bytes: int}>,
     *     candidate: array<string, array{time_us: list<float>, peak_bytes: int}>,
     * }
     */
    public static function measure(callable $round, bool $opcache): array
    {
        $samples = ['baseline' => [], 'candidate' => []];
        for ($index = 0; $index < self::rounds($opcache); ++$index) {
            foreach ($index % 2 === 0 ? ['baseline', 'candidate'] : ['candidate', 'baseline'] as $version) {
                foreach ($round($version, $index) as $name => $measured) {
                    $previous = $samples[$version][$name] ?? ['time_us' => [], 'peak_bytes' => 0];
                    $samples[$version][$name] = [
                        'time_us' => [...$previous['time_us'], ...$measured['time_us']],
                        'peak_bytes' => max($previous['peak_bytes'], $measured['peak_bytes']),
                    ];
                }
            }
        }
        return $samples;
    }

    /**
     * Starts one pool, primes each document with a discarded request, and times further requests per document.
     *
     * @param array<string, string> $settings
     * @param non-empty-list<string> $names
     * @return array<string, array{time_us: non-empty-list<float>, peak_bytes: int}>
     */
    public static function run(string $workspace, string $destination, bool $opcache, array $settings, array $names): array
    {
        $socket = sys_get_temp_dir() . '/json-bench-' . bin2hex(random_bytes(6)) . '.sock';
        $config = $destination . '.conf';
        $log = $destination . '.log';
        file_put_contents(
            $config,
            "[global]\ndaemonize = no\nerror_log = $log\n[benchmark]\nlisten = $socket\npm = static\npm.max_children = 1\ncatch_workers_output = yes\nclear_env = yes\n",
        );
        $configuredBinary = getenv('BENCHMARK_FPM_BINARY');
        $binary = $configuredBinary !== false && $configuredBinary !== '' ? $configuredBinary : 'php-fpm8.4';
        $arguments = [$binary, '--nodaemonize', '--fpm-config', $config];
        foreach (array_replace($settings, [
            'opcache.enable' => $opcache ? '1' : '0',
            'opcache.validate_timestamps' => '0',
        ]) as $name => $value) {
            $arguments[] = '-d';
            $arguments[] = $name . '=' . $value;
        }
        $process = proc_open(
            $arguments,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $workspace,
        );
        if ($process === false) {
            throw new RuntimeException('Cannot start PHP-FPM.');
        }
        try {
            $deadline = microtime(true) + 10;
            while (!file_exists($socket)) {
                $status = proc_get_status($process);
                if (!$status['running'] || microtime(true) >= $deadline) {
                    throw new RuntimeException('PHP-FPM did not start: ' . file_get_contents($log));
                }
                usleep(10_000);
            }
            $result = $raw = [];
            foreach ($names as $name) {
                $times = $memory = $requests = [];
                $worker = null;
                for ($index = 0; $index <= self::requests($opcache); ++$index) {
                    $response = BenchmarkCommand::run([
                        'env',
                        'SCRIPT_FILENAME=' . __DIR__ . '/fpm/request.php',
                        'BENCHMARK_WORKSPACE=' . $workspace,
                        'REQUEST_METHOD=GET',
                        'QUERY_STRING=' . rawurlencode($name),
                        'cgi-fcgi',
                        '-bind',
                        '-connect',
                        $socket,
                    ], $workspace);
                    $sample = self::sample($response, $opcache, $index !== 0);
                    if ($index === 0) {
                        $worker = $sample['pid'];
                        continue;
                    }
                    if ($sample['pid'] !== $worker) {
                        throw new RuntimeException('PHP-FPM replaced the primed worker.');
                    }
                    $times[] = $sample['time_us'];
                    $memory[] = $sample['peak_bytes'];
                    $requests[] = $sample;
                }
                if ($times === [] || $memory === []) {
                    throw new RuntimeException('No PHP-FPM samples.');
                }
                $result[$name] = ['time_us' => $times, 'peak_bytes' => max($memory)];
                $raw[$name] = $requests;
            }
            file_put_contents($destination . '.json', json_encode($raw, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return $result;
        } finally {
            proc_terminate($process);
            proc_close($process);
            if (file_exists($socket)) {
                unlink($socket);
            }
        }
    }

    /** @return array{time_us: float, peak_bytes: int, pid: int, input_bytes: int, php_version: string, opcache_enabled: bool, opcache_misses: int, jit_enabled: bool} */
    public static function sample(string $response, bool $opcache, bool $primed): array
    {
        $parts = explode("\r\n\r\n", $response, 2);
        try {
            $sample = json_decode($parts[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Invalid PHP-FPM response: ' . $response, previous: $error);
        }
        if (
            !is_array($sample)
            || ($sample['opcache_enabled'] ?? null) !== $opcache
            || ($sample['php_version'] ?? null) !== PHP_VERSION
            || ($sample['jit_enabled'] ?? null) !== false
        ) {
            throw new RuntimeException('PHP-FPM runtime does not match the requested configuration: ' . $response);
        }
        $time = $sample['time_us'] ?? null;
        $peak = $sample['peak_bytes'] ?? null;
        $pid = $sample['pid'] ?? null;
        $bytes = $sample['input_bytes'] ?? null;
        $misses = $sample['opcache_misses'] ?? null;
        if (
            !is_int($time) && !is_float($time)
            || !is_finite((float) $time)
            || $time <= 0
            || !is_int($peak)
            || $peak <= 0
            || !is_int($pid)
            || $pid <= 0
            || !is_int($bytes)
            || $bytes <= 0
            || !is_int($misses)
            || $misses < 0
        ) {
            throw new RuntimeException('Invalid PHP-FPM measurement.');
        }
        if ($opcache && $primed && $misses !== 0) {
            throw new RuntimeException('Measured PHP-FPM decode compiled uncached bytecode.');
        }
        return [
            'time_us' => (float) $time,
            'peak_bytes' => $peak,
            'pid' => $pid,
            'input_bytes' => $bytes,
            'php_version' => PHP_VERSION,
            'opcache_enabled' => $opcache,
            'opcache_misses' => $misses,
            'jit_enabled' => false,
        ];
    }

    /**
     * Validates complete sampling, so the aggregation can trust measurements read back from artifacts.
     *
     * @return non-empty-list<FpmComparison>
     */
    public static function compare(mixed $baseline, mixed $candidate, bool $opcache): array
    {
        $count = self::rounds($opcache) * self::requests($opcache);
        $baseline = self::validated($baseline, $count);
        $candidate = self::validated($candidate, $count);
        if (array_keys($baseline) !== array_keys($candidate)) {
            throw new RuntimeException('PHP-FPM workload sets differ.');
        }
        $comparisons = [];
        foreach ($baseline as $name => $base) {
            $comparisons[] = new FpmComparison(
                $name,
                self::median($base['time_us']),
                self::median($candidate[$name]['time_us']),
                $base['peak_bytes'],
                $candidate[$name]['peak_bytes'],
            );
        }
        return $comparisons;
    }

    /** @param non-empty-list<FpmComparison> $comparisons */
    public static function report(array $comparisons, bool $opcache): string
    {
        $lines = [
            '',
            '## Fresh PHP-FPM requests',
            '',
            sprintf(
                '%d alternating pool rounds per revision, each with %d timed requests per document after one discarded priming request.',
                self::rounds($opcache),
                self::requests($opcache),
            ),
            $opcache
                ? 'Bytecode is warm; request-local decoder metadata is fresh.'
                : 'OPcache is off; compilation is included on every request.',
            'Transport, input setup, and hydration/round-trip verification are outside timing. Peak memory covers the whole request.',
            '',
            '| Workload | Target median µs | PR median µs | Change | Peak MiB target / PR |',
            '|---|---:|---:|---:|---:|',
        ];
        foreach ($comparisons as $comparison) {
            $lines[] = sprintf(
                '| %s | %.2f | %.2f | %+.2f%% | %.2f / %.2f |',
                str_replace('|', '\\|', $comparison->workload),
                $comparison->baseline,
                $comparison->candidate,
                $comparison->change(),
                $comparison->baselinePeakBytes / (2 ** 20),
                $comparison->candidatePeakBytes / (2 ** 20),
            );
        }
        return implode("\n", $lines) . "\n";
    }

    /** @return non-empty-array<string, array{time_us: non-empty-list<float>, peak_bytes: int}> */
    private static function validated(mixed $samples, int $count): array
    {
        if (!is_array($samples) || $samples === []) {
            throw new RuntimeException('Missing PHP-FPM measurements.');
        }
        $validated = [];
        foreach ($samples as $name => $sample) {
            $times = is_array($sample) ? $sample['time_us'] ?? null : null;
            $peak = is_array($sample) ? $sample['peak_bytes'] ?? null : null;
            if (
                !is_string($name)
                || !is_array($times)
                || !array_is_list($times)
                || $times === []
                || count($times) !== $count
                || !is_int($peak)
                || $peak <= 0
            ) {
                throw new RuntimeException('Incomplete PHP-FPM measurements.');
            }
            $floats = [];
            foreach ($times as $time) {
                if (!is_int($time) && !is_float($time) || !is_finite((float) $time) || $time <= 0) {
                    throw new RuntimeException('Invalid PHP-FPM time.');
                }
                $floats[] = (float) $time;
            }
            $validated[$name] = ['time_us' => $floats, 'peak_bytes' => $peak];
        }
        return $validated;
    }

    /** @param non-empty-list<float> $values */
    private static function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
