<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use PhpBench\Math\Distribution;
use RuntimeException;

/** Measures fresh requests in an isolated PHP-FPM pool with warm bytecode. */
final class FpmMeasurements
{
    /**
     * @param array<string, string> $settings
     * @return array<string, array{time_us: non-empty-list<float>, peak_bytes: int}>
     */
    public static function run(string $workspace, string $destination, bool $opcache, array $settings): array
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
            $result = $raw = [];
            foreach ($names as $name) {
                if (!is_string($name)) {
                    throw new RuntimeException('Invalid document name.');
                }
                $times = $memory = $requests = [];
                $worker = null;
                for ($index = 0; $index < 16; ++$index) {
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
                    $time = $sample['time_us'];
                    $peak = $sample['peak_bytes'];
                    $times[] = $time;
                    $memory[] = $peak;
                    $requests[] = $sample;
                }
                if ($times === [] || $memory === []) {
                    throw new RuntimeException('No PHP-FPM samples.');
                }
                $result['PHP-FPM / fresh request / ' . $name] = ['time_us' => $times, 'peak_bytes' => max($memory)];
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
     * @param array<string, array{time_us: non-empty-list<float>, peak_bytes: int}> $baseline
     * @param array<string, array{time_us: non-empty-list<float>, peak_bytes: int}> $candidate
     */
    public static function report(array $baseline, array $candidate, bool $opcache): string
    {
        if (array_keys($baseline) !== array_keys($candidate)) {
            throw new RuntimeException('PHP-FPM workload sets differ.');
        }
        $lines = [
            '',
            '## Fresh PHP-FPM requests',
            '',
            'Advisory request measurements: 15 fresh requests per document after one discarded priming request. Statistics use PHPBench. No FPM threshold has been calibrated.',
            $opcache
                ? 'Bytecode is warm; request-local decoder metadata is fresh.'
                : 'OPcache is off; compilation is included on every request.',
            'Transport, input setup, and hydration/round-trip verification are outside timing. Peak memory covers the whole request.',
            '',
            '| Workload | Base mode µs | Candidate mode µs | Change | RSD base / candidate | Peak MiB base / candidate |',
            '|---|---:|---:|---:|---:|---:|',
        ];
        foreach ($baseline as $name => $a) {
            $b = $candidate[$name];
            $base = new Distribution($a['time_us']);
            $head = new Distribution($b['time_us']);
            $lines[] = sprintf(
                '| %s | %.2f | %.2f | %+.1f%% | %.1f%% / %.1f%% | %.2f / %.2f |',
                str_replace('|', '\\|', $name),
                $base->getMode(),
                $head->getMode(),
                100 * (($head->getMode() / $base->getMode()) - 1),
                $base->getRstdev(),
                $head->getRstdev(),
                $a['peak_bytes'] / (2 ** 20),
                $b['peak_bytes'] / (2 ** 20),
            );
        }
        return implode("\n", $lines) . "\n";
    }
}
