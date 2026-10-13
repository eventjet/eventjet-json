#!/usr/bin/env bash
set -euo pipefail
php <<'PHP'
<?php
require '.github/ci/PerformanceShards.php';
require '.github/ci/performance-summary.php';
use Eventjet\Json\Ci\BenchmarkComparison;
use Eventjet\Json\Ci\PerformanceShards;
$directory = sys_get_temp_dir() . '/performance-shards-' . bin2hex(random_bytes(8));
mkdir($directory);
register_shutdown_function(static function () use ($directory): void {
    foreach (glob($directory . '/*/*') as $path) { unlink($path); }
    foreach (glob($directory . '/*', GLOB_ONLYDIR) as $path) { rmdir($path); }
    rmdir($directory);
});
function measurement(array $indices, int $time = 100): string {
    $xml = '<phpbench><suite><env><opcache><value name="enabled">1</value></opcache></env><benchmark class="Fixture">';
    foreach ($indices as $index) {
        $xml .= '<subject name="bench' . $index . '"><variant revs="1" warmup="0"><parameter-set name="case"/>'
            . str_repeat('<iteration time-avg="' . $time . '"/>', BenchmarkComparison::ITERATIONS) . '</variant></subject>';
    }
    return $xml . '</benchmark></suite></phpbench>';
}
function rounds(string $path, int $index, int $candidateTime = 100): void {
    for ($round = 0; $round < BenchmarkComparison::ROUNDS; $round++) {
        file_put_contents($path . '/baseline-' . $index . '-' . $round . '.xml', measurement([$index]));
        file_put_contents($path . '/candidate-' . $index . '-' . $round . '.xml', measurement([$index], $candidateTime));
    }
}
function jsonFile(string $path, array $data): void { file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR)); }
for ($shard = 1; $shard <= PerformanceShards::SHARDS; $shard++) {
    $path = $directory . '/' . $shard;
    mkdir($path);
    jsonFile($path . '/metadata.json', ['shard' => $shard, 'opcache' => 'on', 'fpm' => $shard === 1,
        'baseline' => 'base', 'candidate' => 'candidate', 'workloads' => 'suite',
        'dependencies_sha256' => 'deps', 'configuration_sha256' => 'config']);
    file_put_contents($path . '/discovery.xml', measurement(range(0, 8)));
    $indices = array_values(array_filter(range(0, 8), static fn(int $index): bool => $index % PerformanceShards::SHARDS === $shard - 1));
    jsonFile($path . '/complete.json', ['indices' => $indices]);
    foreach ($indices as $index) {
        rounds($path, $index);
    }
}
function fpmSample(bool $opcache): array {
    $count = Eventjet\Json\Ci\FpmMeasurements::rounds($opcache) * Eventjet\Json\Ci\FpmMeasurements::requests($opcache);
    return ['document' => ['time_us' => array_fill(0, $count, 100.0), 'peak_bytes' => 1024]];
}
$fpmSample = fpmSample(true);
jsonFile($directory . '/1/fpm.json', ['baseline' => $fpmSample, 'candidate' => $fpmSample]);
$result = PerformanceShards::collect($directory, 'on');
if (performanceStatus($result['comparisons'], 'on', $result['fpm']) !== 0 || count($result['comparisons']) !== 9 || count($result['fpm']) !== 1
    || !str_contains(performanceSummary($result['comparisons'], 'on', $result['fpm']), "⚪ No significant changes · **Overall decode time: +0.00%** · **9 workloads**")) {
    throw new RuntimeException('Complete shards must produce one full comparison');
}
$measurementPath = $directory . '/1/candidate-0-0.xml';
rename($measurementPath, $measurementPath . '.saved');
if (!str_contains(performanceSummary($result['comparisons'], 'on', $result['fpm']), '**9 workloads**')) {
    throw new RuntimeException('Summary must use validated comparisons without reopening measurement files');
}
rename($measurementPath . '.saved', $measurementPath);
for ($count = 0; $count <= 25; $count++) {
    $all = PerformanceShards::assignedIndices($count, null);
    $partitioned = [];
    for ($shard = 1; $shard <= PerformanceShards::SHARDS; $shard++) {
        $partitioned = [...$partitioned, ...PerformanceShards::assignedIndices($count, $shard)];
    }
    sort($partitioned);
    if ($partitioned !== $all || count($all) !== $count) {
        throw new RuntimeException('Shard assignments must partition every workload exactly once');
    }
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
rejects($directory, '2/complete.json', '{"indices":[]}');
rejects($directory, '2/complete.json', '{"indices":[1,9]}');
$metadata = json_decode(file_get_contents($directory . '/2/metadata.json'), true);
foreach (['shard' => 1, 'opcache' => 'off', 'fpm' => true, 'baseline' => 'wrong', 'dependencies_sha256' => 'wrong', 'configuration_sha256' => 'wrong'] as $key => $value) {
    rejects($directory, '2/metadata.json', json_encode(array_replace($metadata, [$key => $value])));
}
rejects($directory, '2/discovery.xml', measurement(range(0, 7)));
rejects($directory, '1/fpm.json', json_encode(['baseline' => $fpmSample]));
rejects($directory, '1/fpm.json', json_encode(['baseline' => $fpmSample, 'candidate' => ['document' => ['time_us' => [100.0], 'peak_bytes' => 1024]]]));
rejects($directory, '1/fpm.json', json_encode(['baseline' => fpmSample(false), 'candidate' => fpmSample(false)]));
$last = '2/candidate-1-' . (BenchmarkComparison::ROUNDS - 1) . '.xml';
rejects($directory, $last, measurement([0]));
rejects($directory, $last, preg_replace('{<iteration [^>]*/>}', '', measurement([1]), 1));
rejects($directory, $last, str_replace('>1</value>', '>0</value>', measurement([1])));
rejects($directory, $last, str_replace('revs="1"', 'revs="2"', measurement([1])));
rejects($directory, $last, str_replace('warmup="0"', 'warmup="1"', measurement([1])));
rejects($directory, $last, measurement([1], 0));
function rejectsRounds(string $directory): void {
    try {
        PerformanceShards::collect($directory, 'on');
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('Accepted a shard with missing or extra rounds');
}
$extra = $directory . '/2/candidate-1-' . BenchmarkComparison::ROUNDS . '.xml';
file_put_contents($extra, measurement([1]));
rejectsRounds($directory);
unlink($extra);
rename($directory . '/' . $last, $directory . '/' . $last . '.saved');
rejectsRounds($directory);
rename($directory . '/' . $last . '.saved', $directory . '/' . $last);
rejects($directory, '2/discovery.xml', measurement([0, 0]));
rejects($directory, '2/discovery.xml', str_replace('<subject name="bench0">', '<subject name="bench0"><group name="documents"/><group name="errors"/>', measurement(range(0, 8))));
rounds($directory . '/2', 1, 106);
$result = PerformanceShards::collect($directory, 'on');
if (performanceStatus($result['comparisons'], 'on', $result['fpm']) !== 2) { throw new RuntimeException('Regression verdict was lost'); }
foreach (glob($directory . '/*/metadata.json') as $path) {
    $metadata = json_decode(file_get_contents($path), true);
    $metadata['opcache'] = 'off';
    jsonFile($path, $metadata);
}
foreach (glob($directory . '/*/*.xml') as $path) {
    file_put_contents($path, str_replace('>1</value>', '></value>', file_get_contents($path)));
}
jsonFile($directory . '/1/fpm.json', ['baseline' => fpmSample(false), 'candidate' => fpmSample(false)]);
$result = PerformanceShards::collect($directory, 'off');
if (performanceStatus($result['comparisons'], 'off', $result['fpm']) !== 2) {
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

php <<'PHP'
<?php
require '.github/ci/PerformanceShards.php';
require '.github/ci/performance-summary.php';
use Eventjet\Json\Ci\BenchmarkComparison;
use Eventjet\Json\Ci\BenchmarkWorkload;
$comparison = static fn(string $subject, bool $cold, float $time): BenchmarkComparison
    => new BenchmarkComparison(new BenchmarkWorkload('Fixture', $subject, 'case', 'documents', $cold), 100.0, $time);
if (BenchmarkComparison::LIMIT_PERCENT !== 3.0) {
    throw new LogicException('The boundary cases below assume a 3% limit');
}
foreach ([
    [50, 100, 200, 0, '🟢 Performance improvement', '| 2 | 1 | 0 |', '-37.00%'],
    [96, 100, 100, 0, '🟢 Performance improvement', '| 2 | 1 | 0 |', '-2.68%'],
    [97, 100, 100, 0, '⚪ No significant performance changes', '| 0 | 3 | 0 |', '-2.01%'],
    [102, 100, 100, 0, '⚪ No significant performance changes', '| 0 | 3 | 0 |', '+1.33%'],
    [103, 100, 100, 0, '⚪ No significant performance changes', '| 0 | 3 | 0 |', '+1.99%'],
    [104, 100, 50, 2, '🔴 Performance regression', '| 0 | 1 | 2 |', '+2.65%'],
    [104, 50, 100, 2, '🔴 Performance regression', '| 1 | 0 | 2 |', '-18.53%'],
    [100, 100, 50, 0, '⚪ No significant performance changes', '| 0 | 3 | 0 |', '+0.00%'],
] as [$time, $control, $cold, $status, $verdict, $counts, $overall]) {
    $comparisons = [
        $comparison('first', false, $time),
        $comparison('second', false, $time),
        $comparison('control', false, $control),
        $comparison('cold', true, $cold),
    ];
    $summary = performanceSummary($comparisons, 'on', null);
    if (performanceStatus($comparisons, 'on', null) !== $status) {
        throw new RuntimeException('Incorrect status at ' . $time . ' µs');
    }
    foreach ([$verdict, $counts, $overall, '**3 workloads**', '**1 workloads**', '### Fresh CLI processes · advisory'] as $text) {
        if (!str_contains($summary, $text)) {
            throw new RuntimeException('Missing summary text at ' . $time . ' µs: ' . $text . "\n" . $summary);
        }
    }
}
if (!str_contains(performanceSummary([$comparison('cold', true, 50)], 'on', null), '🟢 Improvement · **Overall decode time: -50.00%** · **1 workloads**')) {
    throw new RuntimeException('Advisory improvements must be shown');
}
if (BenchmarkComparison::order(0) !== ['baseline', 'candidate'] || BenchmarkComparison::order(1) !== ['candidate', 'baseline']) {
    throw new RuntimeException('Rounds must alternate in ABBA order');
}
echo "CLI verdicts respect the limit boundaries in both directions; cold workloads stay advisory.\n";
PHP
