<?php

declare(strict_types=1);

use Eventjet\Json\Ci\BenchmarkCommand;
use Eventjet\Json\Ci\BenchmarkComparison;
use Eventjet\Json\Ci\BenchmarkReport;
use Eventjet\Json\Ci\BenchmarkRuntime;
use Eventjet\Json\Ci\FpmMeasurements;
use Eventjet\Json\Ci\PerformanceShards;

require_once __DIR__ . '/performance-summary.php';
require_once __DIR__ . '/FpmMeasurements.php';
require __DIR__ . '/BenchmarkCommand.php';
require __DIR__ . '/BenchmarkRuntime.php';
require __DIR__ . '/PerformanceShards.php';

function git(string ...$arguments): string
{
    return BenchmarkCommand::run(['git', ...array_values($arguments)]);
}

function exportRevision(string $ref, string $destination): void
{
    // git archive honors export-ignore, which would omit benchmark fixtures.
    $names = explode("\0", git('ls-tree', '-rz', '--name-only', $ref, '--', 'src', 'benchmarks', 'tests'));
    foreach ($names as $name) {
        if ($name === '') {
            continue;
        }
        $path = $destination . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, git('show', $ref . ':' . $name));
    }
}

/** @param array<array-key, mixed> $data */
function writeJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}

function publish(string $results, string $text): void
{
    file_put_contents($results . '/summary.md', $text, FILE_APPEND);
    if (!file_exists($results . '/comment.md')) {
        file_put_contents($results . '/comment.md', $text);
    }
    $summary = getenv('GITHUB_STEP_SUMMARY');
    if ($summary !== false && $summary !== '') {
        file_put_contents($summary, $text, FILE_APPEND);
    }
    echo $text;
}

/** @return array<string, string> */
function configureWorkloads(string $revision, string $workspace, bool $opcache): array
{
    $config = json_decode(git('show', $revision . ':phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($config)) {
        throw new InvalidArgumentException('Benchmark configuration must be an object');
    }
    $config['runner.env_enabled_providers'] = ['php', 'uname', 'opcache', 'unix_sysload'];
    $settings = BenchmarkRuntime::settings($config, $opcache);
    $config['runner.php_config'] = $settings;
    writeJson($workspace . '/phpbench.json', $config);
    return $settings;
}

/** @return array<string, string> */
function prepareWorkspace(
    string $workspace,
    string $source,
    string $workloadFiles,
    string $workloads,
    bool $opcache,
): array {
    mkdir($workspace);
    BenchmarkCommand::run(['cp', '-a', 'vendor', $workspace . '/vendor']);
    copy('composer.json', $workspace . '/composer.json');
    $settings = configureWorkloads($workloads, $workspace, $opcache);
    BenchmarkCommand::run(['cp', '-a', $source . '/src', $workspace . '/src']);
    foreach (['benchmarks', 'tests'] as $name) {
        BenchmarkCommand::run(['cp', '-a', $workloadFiles . '/' . $name, $workspace . '/' . $name]);
    }
    BenchmarkCommand::run(['composer', 'dump-autoload', '--optimize', '--no-interaction'], $workspace);
    return $settings;
}

/**
 * @template T
 * @param callable(string): T $run
 * @return T
 */
function withWorkspace(string $prepared, callable $run): mixed
{
    // Identical runtime paths keep filesystem and autoloader layout out of the comparison.
    $workspace = dirname($prepared) . '/workspace';
    if (file_exists($workspace)) {
        throw new RuntimeException('A benchmark workspace is already active');
    }
    rename($prepared, $workspace);
    try {
        return $run($workspace);
    } finally {
        rename($workspace, $prepared);
    }
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$options = getopt('', ['base:', 'candidate:', 'opcache:', 'workloads:', 'fpm:', 'shard:']);
if ($options === false || !isset($options['base']) || !is_string($options['base'])) {
    throw new InvalidArgumentException(
        'Usage: php .github/ci/performance.php --base REF [--candidate REF] [--opcache on|off] [--workloads REF] [--fpm on|off]',
    );
}
$shard = isset($options['shard']) ? PerformanceShards::shardNumber($options['shard']) : null;
$candidateOption = $options['candidate'] ?? 'HEAD';
if (!is_string($candidateOption)) {
    throw new InvalidArgumentException('Candidate must be a single revision');
}
$opcacheOption = $options['opcache'] ?? 'on';
if ($opcacheOption !== 'on' && $opcacheOption !== 'off') {
    throw new InvalidArgumentException('OPcache must be on or off');
}
$opcache = $opcacheOption === 'on';
$fpmOption = $options['fpm'] ?? 'off';
if ($fpmOption !== 'on' && $fpmOption !== 'off') {
    throw new InvalidArgumentException('FPM must be on or off');
}
$fpm = $fpmOption === 'on';
if ($shard !== null && $fpm !== ($shard === 1)) {
    throw new InvalidArgumentException('Sharded comparisons require FPM on shard 1 only');
}
$workloadsOption = $options['workloads'] ?? $options['base'];
if (!is_string($workloadsOption)) {
    throw new InvalidArgumentException('Workloads must be a single revision');
}
$workloads = trim(git('rev-parse', '--verify', $workloadsOption . '^{commit}'));
$base = trim(git('rev-parse', '--verify', $options['base'] . '^{commit}'));
$candidate = trim(git('rev-parse', '--verify', $candidateOption . '^{commit}'));
$output = getcwd() . '/.perf';
if (file_exists($output)) {
    throw new RuntimeException('Move or remove .perf before starting a new comparison');
}
$results = $output . '/results';
mkdir($results, 0777, true);
$dependenciesHash = hash_file('sha256', 'vendor/composer/installed.json');
if ($dependenciesHash === false) {
    throw new RuntimeException('Cannot fingerprint installed dependencies');
}
$metadata = [
    'shard' => $shard,
    'baseline' => $base,
    'workloads' => $workloads,
    'fpm' => $fpm,
    'opcache' => $opcacheOption,
    'candidate' => $candidate,
    'dependencies_sha256' => $dependenciesHash,
    'workloads_changed' =>
        !isset($options['workloads'])
            && git('diff', '--name-only', $base, $candidate, '--', 'benchmarks', 'tests', 'phpbench.json') !== '',
    'cpu' => 'unknown',
];
$cpuInfo = file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES);
if ($cpuInfo === false) {
    throw new RuntimeException('Cannot read CPU information');
}
foreach ($cpuInfo as $line) {
    if (str_starts_with($line, 'model name')) {
        $metadata['cpu'] = trim(explode(':', $line, 2)[1]);
        break;
    }
}
writeJson($results . '/metadata.json', $metadata);
if (trim(git('ls-tree', '--name-only', $workloads, '--', 'benchmarks')) === '') {
    publish(
        $results,
        "## Performance comparison\n\nNo comparable baseline: workload revision `$workloads` has no benchmark suite. Candidate: `$candidate`. No regression verdict is available.\n",
    );
    exit(0);
}
foreach ([$base, $candidate] as $revision) {
    if (trim(git('ls-tree', '-d', '--name-only', $revision, '--', 'src')) === '') {
        throw new InvalidArgumentException('Compared revision has no source directory: ' . $revision);
    }
}
exportRevision($base, $output . '/baseline');
exportRevision($candidate, $output . '/candidate');
exportRevision($workloads, $output . '/workloads');
$workspaces = [];
$runtimeSettings = [];
foreach (['baseline', 'candidate'] as $version) {
    $workspaces[$version] = $output . '/workspace-' . $version;
    $runtimeSettings[$version] = prepareWorkspace(
        $workspaces[$version],
        $output . '/' . $version,
        $output . '/workloads',
        $workloads,
        $opcache,
    );
}
$metadata['configuration_sha256'] = hash_file('sha256', $workspaces['baseline'] . '/phpbench.json');
writeJson($results . '/metadata.json', $metadata);
$measure = static function (string $workspace, string $name, int $iterations, string ...$options) use (
    $results,
    $opcacheOption,
): BenchmarkReport {
    $output = withWorkspace($workspace, static fn(string $active): string => BenchmarkCommand::run([
        PHP_BINARY,
        'vendor/bin/phpbench',
        'run',
        '--iterations=' . $iterations,
        '--progress=none',
        '--report=aggregate',
        '--dump-file=../results/' . $name . '.xml',
        ...array_values($options),
    ], $active));
    file_put_contents($results . '/' . $name . '.txt', $output);
    $report = BenchmarkReport::read($results . '/' . $name . '.xml');
    $report->requireMode($opcacheOption);
    return $report;
};
$discovery = $measure($workspaces['baseline'], 'discovery', 1, '--revs=1');
$comparisons = [];
$indices = PerformanceShards::assignedIndices(count($discovery->workloads), $shard);
foreach ($indices as $index) {
    $workload = $discovery->workloads[$index];
    $rounds = ['baseline' => [], 'candidate' => []];
    for ($round = 0; $round < BenchmarkComparison::ROUNDS; ++$round) {
        foreach (BenchmarkComparison::order($round) as $version) {
            $rounds[$version][] = $measure(
                $workspaces[$version],
                $version . '-' . $index . '-' . $round,
                BenchmarkComparison::ITERATIONS,
                ...$workload->filters(),
            );
        }
    }
    $comparison = BenchmarkReport::compare(
        $rounds['baseline'],
        $rounds['candidate'],
        $workload->identity(),
        $opcacheOption,
    );
    $comparisons[] = $comparison;
    printf("%s: %+.2f%%\n", $workload->identity(), $comparison->change());
}
$fpmComparisons = null;
$fpmReport = '';
if ($fpm) {
    $names = withWorkspace($workspaces['baseline'], FpmMeasurements::names(...));
    $samples = FpmMeasurements::measure(static fn(string $version, int $round): array => withWorkspace(
        $workspaces[$version],
        static fn(string $active): array => FpmMeasurements::run(
            $active,
            $results . '/' . $version . '-fpm-' . $round,
            $opcache,
            $runtimeSettings[$version],
            $names,
        ),
    ), $opcache);
    writeJson($results . '/fpm.json', $samples);
    $fpmComparisons = FpmMeasurements::compare($samples['baseline'], $samples['candidate'], $opcache);
    $fpmReport = FpmMeasurements::report($fpmComparisons, $opcache);
}
$comment = $comparisons === []
    ? "No workloads assigned to this shard.\n"
    : performanceSummary($comparisons, $opcacheOption, $fpmComparisons);
file_put_contents($results . '/comment.md', $comment);
publish(
    $results,
    $comment
    . "\nBaseline: `$base`. Candidate: `$candidate`. OPcache: **$opcacheOption**. Frozen workloads: `$workloads`.\n\n"
    . cliReport($comparisons)
    . $fpmReport,
);
if ($metadata['workloads_changed'] && ($shard === null || $shard === 1)) {
    $candidateWorkspace = $output . '/workspace-candidate-workloads';
    prepareWorkspace($candidateWorkspace, $output . '/candidate', $output . '/candidate', $candidate, $opcache);
    $measure($candidateWorkspace, 'candidate-workloads', 1);
}
writeJson($results . '/complete.json', ['indices' => $indices]);
exit(performanceStatus($comparisons, $opcacheOption, $fpmComparisons));
