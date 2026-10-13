#!/usr/bin/env bash
set -euo pipefail

fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
mkdir "$fixture/workspace"
cp -a benchmarks tests src vendor composer.json "$fixture/workspace/"
(cd "$fixture/workspace" && composer dump-autoload --optimize --no-interaction)
FPM_RESULTS="$fixture" php <<'PHP'
<?php
require 'vendor/autoload.php';
require '.github/ci/BenchmarkCommand.php';
require '.github/ci/FpmMeasurements.php';
require '.github/ci/BenchmarkRuntime.php';
$valid = ['time_us' => 42, 'peak_bytes' => 1024, 'pid' => 123, 'input_bytes' => 100,
    'php_version' => PHP_VERSION, 'opcache_enabled' => true, 'opcache_misses' => 0, 'jit_enabled' => false];
foreach ([
    [[], true, true, true],
    [['opcache_misses' => 12], true, false, true],
    [['opcache_enabled' => false], false, true, true],
    [['opcache_misses' => 1], true, true, false],
    [['php_version' => 'wrong'], true, true, false],
    [['jit_enabled' => true], true, true, false],
    [['opcache_enabled' => false], true, true, false],
    [[], false, true, false],
    [['time_us' => 0], true, true, false],
    [['time_us' => '1e999'], true, true, false],
    [['peak_bytes' => -1], true, true, false],
    [['pid' => null], true, true, false],
    [['input_bytes' => 0], true, true, false],
    [['opcache_misses' => null], true, true, false],
] as [$overrides, $opcache, $primed, $expected]) {
    $response = "Content-Type: application/json\r\n\r\n" . json_encode(array_replace($valid, $overrides), JSON_THROW_ON_ERROR);
    try {
        $sample = Eventjet\Json\Ci\FpmMeasurements::sample($response, $opcache, $primed);
        $passed = $sample['time_us'] === 42.0 && $sample['input_bytes'] === 100;
    } catch (RuntimeException) {
        $passed = false;
    }
    if ($passed !== $expected) {
        throw new RuntimeException('Incorrect FPM response validation: ' . $response);
    }
}
try {
    Eventjet\Json\Ci\FpmMeasurements::sample("Status: 500\r\n\r\nPHP failed", true, true);
    throw new LogicException('Expected malformed FPM response to fail');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'PHP failed')) {
        throw new LogicException('Missing response diagnostic');
    }
}
$directory = getenv('FPM_RESULTS');
$configuration = json_decode(file_get_contents('phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
$prepend = $directory . '/check-settings.php';
file_put_contents($prepend, <<<'CHECK'
<?php
if (ini_get('serialize_precision') !== '7' || ini_get('precision') !== '9') {
    throw new RuntimeException('Frozen workload settings did not reach PHP-FPM');
}
file_put_contents(__DIR__ . '/checked', 'yes');
CHECK);
$configuration['runner.php_config']['serialize_precision'] = '7';
$configuration['runner.php_config']['precision'] = '9';
$configuration['runner.php_config']['auto_prepend_file'] = $prepend;
$names = Eventjet\Json\Ci\FpmMeasurements::names($directory . '/workspace');
if (count($names) !== 9) {
    throw new RuntimeException('Expected all nine document and batch workloads');
}
foreach ([true, false] as $opcache) {
    $settings = Eventjet\Json\Ci\BenchmarkRuntime::settings($configuration, $opcache);
    $samples = Eventjet\Json\Ci\FpmMeasurements::run($directory . '/workspace', $directory . ($opcache ? '/on' : '/off'), $opcache, $settings, $names);
    if (!file_exists($directory . '/checked')) {
        throw new RuntimeException('PHP-FPM did not execute the configured settings check');
    }
    unlink($directory . '/checked');
    if (array_keys($samples) !== $names) {
        throw new RuntimeException('Expected one result per discovered workload');
    }
    foreach ($samples as $sample) {
        if (count($sample['time_us']) !== Eventjet\Json\Ci\FpmMeasurements::REQUESTS) {
            throw new RuntimeException('Expected every timed request per workload and round');
        }
    }
}
echo "Isolated workload requests passed with OPcache on and off, without a workload-owned FPM adapter.\n";
PHP

php <<'PHP'
<?php
require '.github/ci/FpmMeasurements.php';
use Eventjet\Json\Ci\FpmMeasurements;
foreach ([true, false] as $opcache) {
    $order = [];
    $samples = FpmMeasurements::measure(static function (string $version, int $round) use (&$order, $opcache): array {
        $order[] = $version[0];
        return ['document' => ['time_us' => array_fill(0, FpmMeasurements::REQUESTS, 100.0), 'peak_bytes' => $round + 1]];
    }, $opcache);
    $rounds = FpmMeasurements::rounds($opcache);
    if (implode('', array_slice($order, 0, 8)) !== 'bccbbccb' || count($order) !== 2 * $rounds) {
        throw new RuntimeException('Revisions must alternate in ABBA order across all rounds');
    }
    foreach ($samples as $sample) {
        if (count($sample['document']['time_us']) !== $rounds * FpmMeasurements::REQUESTS
            || $sample['document']['peak_bytes'] !== $rounds) {
            throw new RuntimeException('Rounds must be merged into one complete sample per revision');
        }
    }
}
if (FpmMeasurements::rounds(true) * FpmMeasurements::REQUESTS !== 800) {
    throw new RuntimeException('The blocking sample size must match its calibration');
}
$complete = static fn(array $times): array => ['document' => ['time_us' => $times, 'peak_bytes' => 1024]];
$count = 800;
// An even sample count takes the mean of the two middle values; outliers do not move the median.
$baseline = $complete([...array_fill(0, $count / 2, 100.0), ...array_fill(0, $count / 2 - 1, 102.0), 1e6]);
$limit = 1 + FpmMeasurements::LIMIT_PERCENT / 100;
foreach ([
    [101 * $limit, false, false],
    [101 * $limit + 0.01, true, false],
    [101 * (2 - $limit), false, false],
    [101 * (2 - $limit) - 0.01, false, true],
] as [$time, $slower, $faster]) {
    [$comparison] = FpmMeasurements::compare($baseline, $complete(array_fill(0, $count, $time)), true);
    if ($comparison->baseline !== 101.0 || $comparison->slower() !== $slower || $comparison->faster() !== $faster) {
        throw new RuntimeException('Incorrect FPM limit at ' . $time . ' µs');
    }
}
foreach ([
    [$complete(array_fill(0, $count - 1, 100.0)), $baseline],
    [$baseline, ['other' => $baseline['document']]],
    [$baseline, $complete([...array_fill(0, $count - 1, 100.0), 0])],
    [$baseline, $complete([...array_fill(0, $count - 1, 100.0), 'fast'])],
    [$baseline, ['document' => ['time_us' => array_fill(0, $count, 100.0), 'peak_bytes' => 0]]],
    [$baseline, null],
    [[], []],
] as [$base, $candidate]) {
    try {
        FpmMeasurements::compare($base, $candidate, true);
    } catch (RuntimeException) {
        continue;
    }
    throw new RuntimeException('Accepted incomplete or invalid FPM measurements');
}
if (!FpmMeasurements::blocks(true) || FpmMeasurements::blocks(false)) {
    throw new RuntimeException('Only PHP-FPM with OPcache on blocks');
}
echo "FPM rounds alternate, merge completely, and apply the median limit.\n";
PHP
