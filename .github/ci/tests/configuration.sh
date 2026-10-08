#!/usr/bin/env bash
set -euo pipefail

# Use PHPBench's deterministic executor to test the real assertion and exit status.
project=$(cd "$(dirname "$0")/../../.." && pwd)
php /dev/stdin "$project" <<'PHP'
<?php
require $argv[1] . '/vendor/autoload.php';
$default = \PhpBench\PhpBench::loadContainer(new \Symfony\Component\Console\Input\ArgvInput(['phpbench']), $argv[1]);
$off = \PhpBench\PhpBench::loadContainer(new \Symfony\Component\Console\Input\ArgvInput(['phpbench', '--profile=opcache-off']), $argv[1]);
$expected = array_replace($default->getParameter('runner.php_config'), ['opcache.enable_cli' => '0']);
if ($off->getParameter('runner.php_config') !== $expected) {
    throw new RuntimeException('The OPcache-off profile must preserve all other runtime settings');
}
PHP
echo 'PHPBench profile configuration passed.'
runner="$project/.github/ci/performance.php"
export PERFORMANCE_TEST_PHPBENCH="$project/vendor/bin/phpbench"
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
unset GIT_DIR GIT_WORK_TREE
cd "$fixture"
git init --quiet
git config user.name 'Performance configuration test'
git config user.email 'performance-test@example.invalid'
mkdir -p src benchmarks tests vendor/bin vendor/composer
printf '100\n' > src/time.txt
touch tests/placeholder
printf '%s\n' '{"name":"test/performance","autoload":{}}' > composer.json
printf '%s\n' '{"packages":[]}' > vendor/composer/installed.json
printf '%s\n' '{"runner.path":"benchmarks","runner.php_config":{"serialize_precision":"7","pcov.enabled":"1"}}' > phpbench.json
cat > benchmarks/ExampleBench.php <<'PHP'
<?php
final class ExampleBench
{
    #[\PhpBench\Attributes\ParamProviders('cases')]
    public function benchExample(): void {}
    public function benchControl(): void {}
    public function cases(): iterable
    {
        yield 'first[case]' => [];
        yield 'second.case' => [];
    }
}
PHP
git add src benchmarks tests composer.json phpbench.json
git commit --quiet -m baseline
baseline=$(git rev-parse HEAD)
printf '%s\n' '{"runner.path":"benchmarks","runner.php_config":{"serialize_precision":"9","pcov.enabled":"1"}}' > phpbench.json
git add phpbench.json
git commit --quiet -m candidate
candidate=$(git rev-parse HEAD)
printf '%s\n' '{"runner.path":"uncommitted"}' > phpbench.json

cat > vendor/bin/phpbench <<'PHP'
<?php
declare(strict_types=1);
$config = json_decode(file_get_contents('phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
$candidateSuite = in_array('--dump-file=../results/candidate-workloads.xml', $argv, true);
if ($config['runner.path'] !== 'benchmarks'
    || $config['runner.php_config']['serialize_precision'] !== ($candidateSuite ? '9' : '7')) {
    throw new RuntimeException('Workload configuration was not isolated');
}
foreach (['pcov.enabled', 'opcache.jit', 'opcache.jit_buffer_size', 'opcache.file_update_protection'] as $setting) {
    if ($config['runner.php_config'][$setting] !== '0') {
        throw new RuntimeException('Controlled PHP settings were lost');
    }
}
$expectedOpcache = getenv('EXPECTED_OPCACHE') === '0' ? '0' : '1';
if ($config['runner.php_config']['opcache.enable_cli'] !== $expectedOpcache
    || $config['runner.php_config']['opcache.enable'] !== '1'
    || $config['runner.php_config']['opcache.file_cache'] !== ''
    || $config['runner.php_config']['opcache.save_comments'] !== '1'
    || $config['runner.php_config']['xdebug.mode'] !== 'off') {
    throw new RuntimeException('OPcache mode or isolation settings were lost');
}
$time = (int) trim(file_get_contents('src/time.txt'));
foreach ($argv as $argument) {
    if (str_contains($argument, 'benchControl')) {
        $time = 100;
    }
}
$arguments = [PHP_BINARY, getenv('PERFORMANCE_TEST_PHPBENCH'), ...array_slice($argv, 1),
    '--executor=' . json_encode(['executor' => 'debug', 'times' => [$time]])];
passthru(implode(' ', array_map(escapeshellarg(...), $arguments)), $status);
exit($status);
PHP

check_run() {
    local expected=$1 name=$2 status=0
    shift 2
    php "$runner" --base "$baseline" --candidate "$candidate" "$@" > "$name.log" 2>&1 || status=$?
    if [ "$status" -ne "$expected" ]; then
        cat "$name.log"
        echo "Expected status $expected, got $status for $name"
        exit 1
    fi
    test -s .perf/results/baseline-0.xml
    test -s .perf/results/candidate-0.xml
    test -s .perf/results/candidate-0.txt
    test -s .perf/results/summary.md
    test -s .perf/results/candidate-workloads.xml
    php <<'PHP'
<?php
$files = glob('.perf/results/candidate-*.xml');
$files = array_filter($files, static fn($file) => !str_contains($file, 'candidate-workloads'));
if (count($files) !== 3) {
    throw new RuntimeException('Expected three distinct workload comparisons');
}
foreach ($files as $file) {
    $xml = simplexml_load_file($file);
    if (count($xml->xpath('//variant')) !== 1 || count($xml->xpath('//baseline-stats')) !== 1) {
        throw new RuntimeException('Each comparison must contain exactly one workload and its baseline');
    }
}
PHP
    mv .perf "$name-results"
}
check_run 0 unchanged
for entry in '50 improvement 0' '104 below-limit 0' '105 at-limit 0' '106 regression 2'; do
    read -r time name expected <<< "$entry"
    printf '%s\n' "$time" > src/time.txt
    git add src/time.txt
    git commit --quiet -m "$name"
    candidate=$(git rev-parse HEAD)
    check_run "$expected" "$name"
done
if ! grep -Fq 'PHPBench assertions failed' regression-results/results/summary.md; then
    cat regression.log
    exit 1
fi
echo 'Native PHPBench assertions, boundaries, configuration isolation, and failure artifacts passed.'

EXPECTED_OPCACHE=0 check_run 2 opcache-off --opcache off
php <<'PHP'
<?php
$metadata = json_decode(file_get_contents('opcache-off-results/results/metadata.json'), true, flags: JSON_THROW_ON_ERROR);
if ($metadata['opcache'] !== 'off'
    || !str_contains(file_get_contents('opcache-off-results/results/summary.md'), 'OPcache: **off**')) {
    throw new RuntimeException('Explicit OPcache-off mode was not reported');
}
PHP
if php "$runner" --base "$baseline" --opcache invalid > invalid.log 2>&1; then
    echo 'Expected an invalid OPcache mode to fail'
    exit 1
fi
grep -Fq 'OPcache must be on or off' invalid.log
echo 'OPcache modes passed.'

# Failed subprocesses must retain stdout diagnostics as well as forwarded stderr.
cat > vendor/bin/phpbench <<'PHP'
<?php
fwrite(STDOUT, "Benchmark failure detail on stdout\n");
fwrite(STDERR, "Benchmark failure detail on stderr\n");
exit(23);
PHP
if php "$runner" --base "$baseline" --candidate "$candidate" > failure.log 2>&1; then
    echo 'Expected the benchmark subprocess to fail'
    exit 1
fi
for diagnostic in 'Command failed (23)' 'Benchmark failure detail on stdout' 'Benchmark failure detail on stderr'; do
    if ! grep -Fq "$diagnostic" failure.log; then
        cat failure.log
        echo "Missing failure diagnostic: $diagnostic"
        exit 1
    fi
done
echo 'Subprocess failure diagnostics passed.'
