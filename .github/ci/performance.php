<?php

declare(strict_types=1);

use Eventjet\Json\Ci\PerformanceGate;
use Eventjet\Json\Ci\PerformanceResults;

require __DIR__ . '/PerformanceResults.php';
require __DIR__ . '/PerformanceGate.php';

// This runner targets the same Linux environment as the Performance workflow.
// GNU timeout bounds each subprocess; the workflow also bounds the entire job.
/** @param non-empty-list<string> $arguments */
function command(array $arguments, string|null $directory = null): string
{
    $process = proc_open(
        ['timeout', '90', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR],
        $pipes,
        $directory,
    );
    if ($process === false) {
        throw new RuntimeException('Cannot start command');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    if ($status !== 0 || $output === false) {
        throw new RuntimeException(
            'Command failed (' . $status . '): ' . implode(' ', $arguments) . "\n" . ($output === false ? '' : $output),
        );
    }
    return $output;
}

function git(string ...$arguments): string
{
    return command(['git', ...array_values($arguments)]);
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
    file_put_contents($results . '/summary.md', $text);
    $summary = getenv('GITHUB_STEP_SUMMARY');
    if ($summary !== false && $summary !== '') {
        file_put_contents($summary, $text, FILE_APPEND);
    }
    echo $text;
}

function configureWorkloads(string $revision, string $workspace): void
{
    $config = json_decode(git('show', $revision . ':phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($config)) {
        throw new InvalidArgumentException('Benchmark configuration must be an object');
    }
    $config['runner.env_enabled_providers'] = ['php', 'uname', 'opcache', 'unix_sysload'];
    $phpConfig = $config['runner.php_config'] ?? [];
    if (!is_array($phpConfig)) {
        throw new InvalidArgumentException('Benchmark PHP configuration must be an object');
    }
    $config['runner.php_config'] = array_replace($phpConfig, [
        'pcov.enabled' => '0',
        'opcache.enable_cli' => '0',
        'opcache.jit' => '0',
        'xdebug.mode' => 'off',
        'memory_limit' => '1G',
    ]);
    writeJson($workspace . '/phpbench.json', $config);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$options = getopt('', ['base:', 'candidate:']);
if ($options === false || !isset($options['base']) || !is_string($options['base'])) {
    throw new InvalidArgumentException('Usage: php .github/ci/performance.php --base REF [--candidate REF]');
}
$candidateOption = $options['candidate'] ?? 'HEAD';
if (!is_string($candidateOption)) {
    throw new InvalidArgumentException('Candidate must be a single revision');
}
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
    'candidate' => $candidate,
    'dependencies_sha256' => $dependenciesHash,
    'workloads_changed' =>
        git('diff', '--name-only', $base, $candidate, '--', 'benchmarks', 'tests', 'phpbench.json') !== '',
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
if (trim(git('ls-tree', '--name-only', $base, '--', 'benchmarks')) === '') {
    publish(
        $results,
        "## Performance comparison\n\nNo comparable baseline: `$base` has no benchmark suite. Candidate: `$candidate`. No regression verdict is available.\n",
    );
    exit(0);
}
exportRevision($base, $output . '/baseline');
exportRevision($candidate, $output . '/candidate');
$workspace = $output . '/workspace';
mkdir($workspace);
command(['cp', '-a', 'vendor', $workspace . '/vendor']);
copy('composer.json', $workspace . '/composer.json');
configureWorkloads($base, $workspace);
foreach (['benchmarks', 'tests'] as $name) {
    command(['cp', '-a', $output . '/baseline/' . $name, $workspace . '/' . $name]);
}
$measure = static function (string $version, string $name) use ($workspace, $output, $results): array {
    command(['rm', '-rf', '--', $workspace . '/src']);
    command(['cp', '-a', $output . '/' . $version . '/src', $workspace . '/src']);
    command(['composer', 'dump-autoload', '--optimize', '--no-interaction'], $workspace);
    command([
        PHP_BINARY,
        'vendor/bin/phpbench',
        'run',
        '--iterations=5',
        '--progress=none',
        '--dump-file=../results/' . $name . '.xml',
    ], $workspace);
    $xml = file_get_contents($results . '/' . $name . '.xml');
    if ($xml === false) {
        throw new RuntimeException('Cannot read benchmark output: ' . $name);
    }
    return PerformanceResults::samples($xml);
};
$experiments = [];
foreach (['calibration', 'comparison'] as $experiment) {
    $pairs = [];
    for ($index = 0; $index < 3; ++$index) {
        $pair = [];
        foreach (($index % 2) === 0 ? ['a', 'b'] : ['b', 'a'] as $side) {
            $version = $experiment === 'comparison' && $side === 'b' ? 'candidate' : 'baseline';
            $pair[$side] = $measure($version, "$experiment-$index-$side");
        }
        $pairs[] = [$pair['a'], $pair['b']];
    }
    $experiments[$experiment] = PerformanceResults::summarize($pairs);
}
if ($metadata['workloads_changed']) {
    configureWorkloads($candidate, $workspace);
    foreach (['benchmarks', 'tests'] as $name) {
        command(['rm', '-rf', '--', $workspace . '/' . $name]);
        command(['cp', '-a', $output . '/candidate/' . $name, $workspace . '/' . $name]);
    }
    $measure('candidate', 'candidate-workloads');
}
$thresholdJson = file_get_contents(__DIR__ . '/performance-thresholds.json');
if ($thresholdJson === false) {
    throw new RuntimeException('Cannot read performance thresholds');
}
$thresholds = PerformanceGate::thresholds($thresholdJson);
$verdicts = PerformanceGate::evaluate($experiments['comparison'], $experiments['calibration'], $thresholds);
writeJson($results . '/results.json', [
    'metadata' => $metadata,
    ...$experiments,
    'thresholds' => $thresholds,
    'verdicts' => $verdicts,
]);
publish(
    $results,
    PerformanceResults::report($metadata, $experiments['comparison'], $experiments['calibration'])
        . PerformanceGate::report($verdicts, $thresholds),
);
exit(count(array_filter($verdicts, static fn(string $verdict): bool => $verdict !== 'pass')) > 0 ? 1 : 0);
