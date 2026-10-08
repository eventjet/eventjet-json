<?php

declare(strict_types=1);

use Eventjet\Json\Ci\BenchmarkCommand;
use Eventjet\Json\Ci\BenchmarkRuntime;
use Eventjet\Json\Ci\FpmMeasurements;

require_once __DIR__ . '/performance-summary.php';
const REPORT_GROUPS = [
    'documents' => 'Realistic example documents',
    'batches' => 'Synthetic batches',
    'diagnostic' => 'Focused diagnostics',
    'stress' => 'Stress diagnostics',
    'errors' => 'Expected errors',
    'uncategorized' => 'Uncategorized workloads',
];

require __DIR__ . '/FpmMeasurements.php';
require __DIR__ . '/BenchmarkCommand.php';
require __DIR__ . '/BenchmarkRuntime.php';

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

/** @return non-empty-list<array{string, string, string}> */
function workloadFilters(string $path): array
{
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        throw new RuntimeException('Cannot read workload discovery results');
    }
    $filters = [];
    foreach ($xml->suite as $suite) {
        foreach ($suite->benchmark as $benchmark) {
            foreach ($benchmark->subject as $subject) {
                $categories = [];
                foreach ($subject->group as $group) {
                    $name = (string) $group['name'];
                    if (isset(REPORT_GROUPS[$name])) {
                        $categories[$name] = true;
                    }
                }
                if (count($categories) > 1) {
                    throw new RuntimeException('A benchmark subject must belong to only one report category');
                }
                $category = array_key_first($categories) ?? 'uncategorized';
                foreach ($subject->variant as $variant) {
                    $filters[] = [
                        '--filter=^' . preg_quote($benchmark['class'] . '::' . $subject['name'], '{') . '$',
                        '--variant=^' . preg_quote((string) $variant->{'parameter-set'}['name'], '{') . '$',
                        $category,
                    ];
                }
            }
        }
    }
    if ($filters === []) {
        throw new RuntimeException('No benchmark workloads discovered');
    }
    return $filters;
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

$options = getopt('', ['base:', 'candidate:', 'opcache:', 'workloads:', 'fpm:']);
if ($options === false || !isset($options['base']) || !is_string($options['base'])) {
    throw new InvalidArgumentException(
        'Usage: php .github/ci/performance.php --base REF [--candidate REF] [--opcache on|off] [--workloads REF] [--fpm on|off]',
    );
}
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
$measure = static function (string $workspace, string $name, string|null $baseline = null, string ...$options) use (
    $results,
    $opcache,
): int {
    $arguments = [
        PHP_BINARY,
        'vendor/bin/phpbench',
        'run',
        '--iterations=' . ($name === 'discovery' ? '1' : '20'),
        '--progress=none',
        '--report=aggregate',
        '--dump-file=../results/' . $name . '.xml',
        ...array_values($options),
    ];
    if ($baseline !== null) {
        $arguments[] = '--file=../results/' . $baseline . '.xml';
        $arguments[] = '--assert=mode(variant.time.avg) <= mode(baseline.time.avg) * 1.05';
    }
    $result = withWorkspace($workspace, static fn(string $active): array => BenchmarkCommand::execute(
        $arguments,
        $active,
    ));
    file_put_contents($results . '/' . $name . '.txt', $result['output']);
    echo $result['output'];
    if ($result['status'] !== 0 && ($baseline === null || $result['status'] !== 2)) {
        throw new RuntimeException('Command failed (' . $result['status'] . "): PHPBench\n" . $result['output']);
    }
    $xml = simplexml_load_file($results . '/' . $name . '.xml');
    if ($xml === false) {
        throw new RuntimeException('Cannot read benchmark output: ' . $name);
    }
    $reported = $xml->xpath('//env/opcache/value[@name="enabled"]');
    if ($reported === null || count($reported) !== 1 || ((string) $reported[0] === '1') !== $opcache) {
        throw new RuntimeException('Benchmark OPcache state does not match the requested mode');
    }
    return $result['status'];
};
$measure($workspaces['baseline'], 'discovery', null, '--revs=1');
$status = 0;
$reports = [];
$comparisonPaths = [];
foreach (workloadFilters($results . '/discovery.xml') as $index => [$subjectFilter, $variantFilter, $group]) {
    $filters = [$subjectFilter, $variantFilter];
    $baselineName = 'baseline-' . $index;
    $candidateName = 'candidate-' . $index;
    $measure($workspaces['baseline'], $baselineName, null, ...$filters);
    $status = max($status, $measure($workspaces['candidate'], $candidateName, $baselineName, ...$filters));
    $comparisonPaths[] = $results . '/' . $candidateName . '.xml';
    $workloadReport = file_get_contents($results . '/' . $candidateName . '.txt');
    if ($workloadReport === false) {
        throw new RuntimeException('Cannot read PHPBench report');
    }
    $reports[$group] = ($reports[$group] ?? '') . $workloadReport . "\n";
}
$report = '';
foreach (REPORT_GROUPS as $group => $title) {
    if (isset($reports[$group])) {
        $report .= "\n### $title\n\n```text\n" . $reports[$group] . "\n```\n";
    }
}
$comment = performanceSummary($comparisonPaths, $status);
file_put_contents($results . '/comment.md', $comment);
publish(
    $results,
    $comment
    . "\nBaseline: `$base`. Candidate: `$candidate`. OPcache: **$opcacheOption**. Frozen workloads: `$workloads`.\n\n"
    . ($status === 0 ? 'PHPBench assertions passed.' : 'PHPBench assertions failed: performance regression.')
    . " PHPBench mode time per decode must be at most 105% of the target's mode for every workload.\n\n"
    . 'Each target workload is measured immediately before its candidate counterpart, using the same configuration and dependencies on this runner. '
    . "Cold and warm workloads retain their own warmup and revolution settings. Memory is advisory.\n\n"
    . $report,
);
if ($fpm) {
    require 'vendor/autoload.php';
    $samples = [];
    foreach (['baseline', 'candidate'] as $version) {
        $samples[$version] = withWorkspace($workspaces[$version], static fn(string $active): array => FpmMeasurements::run(
            $active,
            $results . '/' . $version . '-fpm',
            $opcache,
            $runtimeSettings[$version],
        ));
    }
    writeJson($results . '/fpm.json', $samples);
    publish($results, FpmMeasurements::report($samples['baseline'], $samples['candidate'], $opcache));
}
if ($metadata['workloads_changed']) {
    $candidateWorkspace = $output . '/workspace-candidate-workloads';
    prepareWorkspace($candidateWorkspace, $output . '/candidate', $output . '/candidate', $candidate, $opcache);
    $measure($candidateWorkspace, 'candidate-workloads');
}
exit($status);
