#!/usr/bin/env bash
set -euo pipefail
php <<'PHP'
<?php
require '.github/ci/PerformanceShards.php';
require '.github/ci/performance-summary.php';
use Eventjet\Json\Ci\PerformanceShards;
$directory = sys_get_temp_dir() . '/performance-shards-' . bin2hex(random_bytes(8));
mkdir($directory);
register_shutdown_function(static function () use ($directory): void {
    foreach (glob($directory . '/*/*') as $path) { unlink($path); }
    foreach (glob($directory . '/*', GLOB_ONLYDIR) as $path) { rmdir($path); }
    rmdir($directory);
});
function measurement(array $indices, bool $comparison = false): string {
    $xml = '<phpbench><suite><env><opcache><value name="enabled">1</value></opcache></env><benchmark class="Fixture">';
    foreach ($indices as $index) {
        $xml .= '<subject name="bench' . $index . '"><variant revs="1" warmup="0"><parameter-set name="case"/>'
            . str_repeat('<iteration time-net="100"/>', 20) . '<stats mode="100"/>'
            . ($comparison ? '<baseline-stats mode="100"/>' : '') . '</variant></subject>';
    }
    return $xml . '</benchmark></suite></phpbench>';
}
function jsonFile(string $path, array $data): void { file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR)); }
for ($shard = 1; $shard <= 4; $shard++) {
    $path = $directory . '/' . $shard;
    mkdir($path);
    jsonFile($path . '/metadata.json', ['shard' => $shard, 'opcache' => 'on', 'fpm' => $shard === 1,
        'baseline' => 'base', 'candidate' => 'candidate', 'workloads' => 'suite',
        'dependencies_sha256' => 'deps', 'configuration_sha256' => 'config']);
    file_put_contents($path . '/discovery.xml', measurement(range(0, 8)));
    $indices = array_values(array_filter(range(0, 8), static fn(int $index): bool => $index % 4 === $shard - 1));
    jsonFile($path . '/complete.json', ['status' => 0, 'indices' => $indices]);
    foreach ($indices as $index) {
        file_put_contents($path . '/baseline-' . $index . '.xml', measurement([$index]));
        file_put_contents($path . '/candidate-' . $index . '.xml', measurement([$index], true));
    }
}
jsonFile($directory . '/1/fpm.json', ['baseline' => [], 'candidate' => []]);
file_put_contents($directory . '/1/fpm.md', 'FPM report');
$result = PerformanceShards::collect($directory, 'on');
if ($result['status'] !== 0 || count($result['paths']) !== 9 || !str_contains(performanceSummary($result['paths'], 0), '**9 workloads**')) {
    throw new RuntimeException('Complete shards must produce one full comparison');
}
function rejects(string $directory, string $file, string $replacement): void {
    $path = $directory . '/' . $file;
    $original = file_get_contents($path);
    file_put_contents($path, $replacement);
    try {
        PerformanceShards::collect($directory, 'on');
    } catch (RuntimeException) {
        file_put_contents($path, $original);
        return;
    }
    throw new RuntimeException('Accepted invalid shard: ' . $file);
}
rejects($directory, '2/complete.json', '{"status":0,"indices":[]}');
rejects($directory, '2/complete.json', '{"status":1,"indices":[1,5]}');
$metadata = json_decode(file_get_contents($directory . '/2/metadata.json'), true);
foreach (['shard' => 1, 'opcache' => 'off', 'fpm' => true, 'baseline' => 'wrong', 'dependencies_sha256' => 'wrong', 'configuration_sha256' => 'wrong'] as $key => $value) {
    rejects($directory, '2/metadata.json', json_encode(array_replace($metadata, [$key => $value])));
}
rejects($directory, '2/discovery.xml', measurement(range(0, 7)));
rejects($directory, '2/candidate-1.xml', measurement([0], true));
rejects($directory, '2/candidate-1.xml', str_replace('<iteration time-net="100"/>', '', measurement([1], true)));
rejects($directory, '2/candidate-1.xml', str_replace('>1</value>', '>0</value>', measurement([1], true)));
rejects($directory, '2/candidate-1.xml', str_replace('<baseline-stats mode="100"/>', '', measurement([1], true)));
rejects($directory, '2/candidate-1.xml', str_replace('<baseline-stats mode="100"/>', '<baseline-stats mode="99"/>', measurement([1], true)));
jsonFile($directory . '/2/complete.json', ['status' => 2, 'indices' => [1, 5]]);
if (PerformanceShards::collect($directory, 'on')['status'] !== 2) { throw new RuntimeException('Regression verdict was lost'); }
foreach (glob($directory . '/*/metadata.json') as $path) {
    $metadata = json_decode(file_get_contents($path), true);
    $metadata['opcache'] = 'off';
    jsonFile($path, $metadata);
}
foreach (glob($directory . '/*/*.xml') as $path) {
    file_put_contents($path, str_replace('>1</value>', '></value>', file_get_contents($path)));
}
if (PerformanceShards::collect($directory, 'off')['status'] !== 2) {
    throw new RuntimeException('OPcache-off empty XML boolean must preserve the verdict');
}
unlink($directory . '/2/complete.json');
set_error_handler(static function (int $severity, string $message): never { throw new RuntimeException($message); });
try {
    PerformanceShards::collect($directory, 'off');
    throw new LogicException('Incomplete shard accepted');
} catch (RuntimeException) {}
restore_error_handler();
echo "Shard aggregation covers every workload and rejects incomplete, duplicate, or mismatched results.\n";
PHP
