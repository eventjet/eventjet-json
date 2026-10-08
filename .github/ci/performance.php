<?php

declare(strict_types=1);

require_once __DIR__ . '/performance-summary.php';

// This runner targets the same Linux environment as the Performance workflow.
// GNU timeout bounds each subprocess; the workflow also bounds the entire job.
/**
 * @param non-empty-list<string> $arguments
 * @return array{output: string, status: int}
 */
function execute(array $arguments, string|null $directory = null): array
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
    if ($output === false) {
        throw new RuntimeException('Cannot read command output');
    }
    return ['output' => $output, 'status' => $status];
}

/** @param non-empty-list<string> $arguments */
function command(array $arguments, string|null $directory = null): string
{
    $result = execute($arguments, $directory);
    if ($result['status'] !== 0) {
        throw new RuntimeException(
            'Command failed (' . $result['status'] . '): ' . implode(' ', $arguments) . "\n" . $result['output'],
        );
    }
    return $result['output'];
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
    if (!file_exists($results . '/comment.md')) {
        file_put_contents($results . '/comment.md', $text);
    }
    $summary = getenv('GITHUB_STEP_SUMMARY');
    if ($summary !== false && $summary !== '') {
        file_put_contents($summary, $text, FILE_APPEND);
    }
    echo $text;
}

function configureWorkloads(string $revision, string $workspace, bool $opcache): void
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
        'opcache.enable' => '1',
        'opcache.enable_cli' => $opcache ? '1' : '0',
        'opcache.file_update_protection' => '0',
        'opcache.file_cache' => '',
        'opcache.save_comments' => '1',
        'opcache.jit' => '0',
        'opcache.jit_buffer_size' => '0',
        'xdebug.mode' => 'off',
        'memory_limit' => '1G',
    ]);
    writeJson($workspace . '/phpbench.json', $config);
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
                foreach ($subject->variant as $variant) {
                    $filters[] = [
                        '--filter=^' . preg_quote($benchmark['class'] . '::' . $subject['name'], '{') . '$',
                        '--variant=^' . preg_quote((string) $variant->{'parameter-set'}['name'], '{') . '$',
                        match (true) {
                            str_contains((string) $benchmark['class'], 'ErrorBench') => 'Expected errors',
                            str_contains((string) $variant->{'parameter-set'}['name'], 'record batch')
                                => 'Synthetic batches',
                            str_contains((string) $benchmark['class'], 'DocumentBench')
                                => 'Realistic example documents',
                            str_contains((string) $variant->{'parameter-set'}['name'], 'collections')
                                || str_contains((string) $variant->{'parameter-set'}['name'], 'enum-heavy')
                                => 'Stress diagnostics',
                            default => 'Focused diagnostics',
                        },
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

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$options = getopt('', ['base:', 'candidate:', 'opcache:', 'workloads:']);
if ($options === false || !isset($options['base']) || !is_string($options['base'])) {
    throw new InvalidArgumentException(
        'Usage: php .github/ci/performance.php --base REF [--candidate REF] [--opcache on|off] [--workloads REF]',
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
if (
    trim(git('ls-tree', '--name-only', $base, '--', 'benchmarks')) === ''
    || trim(git('ls-tree', '--name-only', $workloads, '--', 'benchmarks')) === ''
) {
    publish(
        $results,
        "## Performance comparison\n\nNo comparable baseline: baseline `$base` or workload revision `$workloads` has no benchmark suite. Candidate: `$candidate`. No regression verdict is available.\n",
    );
    exit(0);
}
exportRevision($base, $output . '/baseline');
exportRevision($candidate, $output . '/candidate');
exportRevision($workloads, $output . '/workloads');
$workspace = $output . '/workspace';
mkdir($workspace);
command(['cp', '-a', 'vendor', $workspace . '/vendor']);
copy('composer.json', $workspace . '/composer.json');
configureWorkloads($workloads, $workspace, $opcache);
foreach (['benchmarks', 'tests'] as $name) {
    command(['cp', '-a', $output . '/workloads/' . $name, $workspace . '/' . $name]);
}
$measure = static function (string $version, string $name, string|null $baseline = null, string ...$options) use (
    $workspace,
    $output,
    $results,
    $opcache,
): int {
    command(['rm', '-rf', '--', $workspace . '/src']);
    command(['cp', '-a', $output . '/' . $version . '/src', $workspace . '/src']);
    command(['composer', 'dump-autoload', '--optimize', '--no-interaction'], $workspace);
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
    $result = execute($arguments, $workspace);
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
$measure('baseline', 'discovery', null, '--revs=1');
$status = 0;
$reports = [];
$comparisonPaths = [];
foreach (workloadFilters($results . '/discovery.xml') as $index => [$subjectFilter, $variantFilter, $group]) {
    $filters = [$subjectFilter, $variantFilter];
    $baselineName = 'baseline-' . $index;
    $candidateName = 'candidate-' . $index;
    $measure('baseline', $baselineName, null, ...$filters);
    $status = max($status, $measure('candidate', $candidateName, $baselineName, ...$filters));
    $comparisonPaths[] = $results . '/' . $candidateName . '.xml';
    $workloadReport = file_get_contents($results . '/' . $candidateName . '.txt');
    if ($workloadReport === false) {
        throw new RuntimeException('Cannot read PHPBench report');
    }
    $reports[$group] = ($reports[$group] ?? '') . $workloadReport . "\n";
}
$report = '';
foreach ([
    'Realistic example documents',
    'Synthetic batches',
    'Focused diagnostics',
    'Stress diagnostics',
    'Expected errors',
] as $group) {
    if (isset($reports[$group])) {
        $report .= "\n### $group\n\n```text\n" . $reports[$group] . "\n```\n";
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
if ($metadata['workloads_changed']) {
    configureWorkloads($candidate, $workspace, $opcache);
    foreach (['benchmarks', 'tests'] as $name) {
        command(['rm', '-rf', '--', $workspace . '/' . $name]);
        command(['cp', '-a', $output . '/candidate/' . $name, $workspace . '/' . $name]);
    }
    $measure('candidate', 'candidate-workloads');
}
exit($status);
