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
foreach ([true, false] as $opcache) {
    $settings = Eventjet\Json\Ci\BenchmarkRuntime::settings($configuration, $opcache);
    $samples = Eventjet\Json\Ci\FpmMeasurements::run($directory . '/workspace', $directory . ($opcache ? '/on' : '/off'), $opcache, $settings);
    if (!file_exists($directory . '/checked')) {
        throw new RuntimeException('PHP-FPM did not execute the configured settings check');
    }
    unlink($directory . '/checked');
    $report = Eventjet\Json\Ci\FpmMeasurements::report($samples, $samples, $opcache);
    if (substr_count($report, '+0.0%') !== 9) {
        throw new RuntimeException('Incorrect unchanged-code FPM report');
    }
    if (count($samples) !== 9) {
        throw new RuntimeException('Expected all nine document and batch workloads');
    }
    foreach ($samples as $sample) {
        if (count($sample['time_us']) !== 15) {
            throw new RuntimeException('Expected 15 fresh requests per workload');
        }
    }
}
echo "Isolated workload requests passed with OPcache on and off, without a workload-owned FPM adapter.\n";
PHP
