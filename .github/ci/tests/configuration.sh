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
export PERFORMANCE_TEST_COMPOSER=$(command -v composer)
export PERFORMANCE_TEST_PHPBENCH="$project/vendor/bin/phpbench"
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
unset GIT_DIR GIT_WORK_TREE
cd "$fixture"
git init --quiet
git config user.name 'Performance configuration test'
git config user.email 'performance-test@example.invalid'
mkdir -p src benchmarks tests vendor/bin vendor/composer bin
export PATH="$fixture/bin:$PATH"
cat > bin/composer <<'SH'
#!/usr/bin/env bash
set -euo pipefail
pwd >> ../preparations.txt
exec "$PERFORMANCE_TEST_COMPOSER" "$@"
SH
chmod +x bin/composer
cat > src/Revision.php <<'PHP'
<?php
namespace Fixture;
final class Revision
{
    public const IDENTITY = 'baseline';
    public static function time(): int
    {
        if (__DIR__ !== getcwd() . '/src') {
            throw new \RuntimeException('Source autoloader escaped its workspace');
        }
        return (int) trim(file_get_contents(__DIR__ . '/time.txt'));
    }
}
PHP
printf '100\n' > src/time.txt
touch tests/placeholder
printf '%s\n' '{"name":"test/performance","autoload":{"psr-4":{"Fixture\\":"src/"}}}' > composer.json
printf '%s\n' '{"packages":[]}' > vendor/composer/installed.json
printf '%s\n' '{"runner.path":"benchmarks","runner.php_config":{"serialize_precision":"7","pcov.enabled":"1"}}' > phpbench.json
cat > benchmarks/ExampleBench.php <<'PHP'
<?php
final class ErrorBench
{
    #[\PhpBench\Attributes\Groups(['documents', 'warm'])]
    #[\PhpBench\Attributes\ParamProviders('cases')]
    public function benchExample(): void {}
    public function benchControl(): void {}
    #[\PhpBench\Attributes\Groups(['documents', 'cold'])]
    public function benchCold(): void {}
    public function cases(): iterable
    {
        yield 'record batch[case]' => [];
        yield 'enum-heavy.collections' => [];
    }
}
PHP
git add src benchmarks tests composer.json phpbench.json
git commit --quiet -m baseline
baseline=$(git rev-parse HEAD)
sed -i "s/IDENTITY = 'baseline'/IDENTITY = 'candidate'/" src/Revision.php
printf '%s\n' '{"runner.path":"benchmarks","runner.php_config":{"serialize_precision":"9","pcov.enabled":"1"}}' > phpbench.json
git add phpbench.json src/Revision.php
git commit --quiet -m candidate
candidate=$(git rev-parse HEAD)
printf '%s\n' '{"runner.path":"uncommitted"}' > phpbench.json

cat > vendor/bin/phpbench <<'PHP'
<?php
declare(strict_types=1);
$config = json_decode(file_get_contents('phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
$candidateSuite = in_array('--dump-file=../results/candidate-workloads.xml', $argv, true) || getenv('EXPLICIT_WORKLOADS') === '1';
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
require 'vendor/autoload.php';
if (!str_ends_with(getcwd(), '/.perf/workspace')) {
    throw new RuntimeException('Compared revisions must share one runtime path');
}
$baselineRun = false;
foreach ($argv as $argument) {
    $baselineRun = $baselineRun
        || $argument === '--dump-file=../results/discovery.xml'
        || str_starts_with($argument, '--dump-file=../results/baseline-');
}
$expectedIdentity = $baselineRun && getenv('SOURCE_ONLY') !== '1' ? 'baseline' : 'candidate';
if (\Fixture\Revision::IDENTITY !== $expectedIdentity) {
    throw new RuntimeException('Autoloader selected the wrong compared revision');
}
$time = \Fixture\Revision::time();
$cold = false;
$compared = false;
$asserted = false;
foreach ($argv as $argument) {
    if (str_contains($argument, 'benchControl')) {
        $time = $time === 100 ? 100 : (int) (getenv('PERFORMANCE_TEST_CONTROL_TIME') ?: 100);
    }
    $cold = $cold || str_contains($argument, 'benchCold');
    $compared = $compared || str_starts_with($argument, '--file=');
    $asserted = $asserted || str_starts_with($argument, '--assert=');
}
if ($cold && \Fixture\Revision::IDENTITY === 'candidate' && getenv('PERFORMANCE_TEST_COLD_TIME') !== false) {
    $time = (int) getenv('PERFORMANCE_TEST_COLD_TIME');
}
if ($asserted !== ($compared && !$cold)) {
    throw new RuntimeException('Only warm comparisons may assert');
}
$arguments = [PHP_BINARY, getenv('PERFORMANCE_TEST_PHPBENCH'), ...array_slice($argv, 1),
    '--executor=' . json_encode(['executor' => 'debug', 'times' => [$time]])];
passthru(implode(' ', array_map(escapeshellarg(...), $arguments)), $status);
exit($status);
PHP

check_run() {
    local expected=$1 name=$2 verdict=$3 counts=$4 overall=$5 status=0
    shift 5
    php "$runner" --base "$baseline" --candidate "$candidate" "$@" > "$name.log" 2>&1 || status=$?
    if [ "$status" -ne "$expected" ]; then
        cat "$name.log"
        echo "Expected status $expected, got $status for $name"
        exit 1
    fi
    test ! -e .perf/workspace
    test -d .perf/workspace-baseline
    test -d .perf/workspace-candidate
    test -d .perf/workspace-candidate-workloads
    test "$(wc -l < .perf/preparations.txt)" -eq 3
    test "$(sort -u .perf/preparations.txt | wc -l)" -eq 3
    test -s .perf/results/baseline-0.xml
    test -s .perf/results/candidate-0.xml
    test -s .perf/results/candidate-0.txt
    test -s .perf/results/summary.md
    test -s .perf/results/comment.md
    for text in "$verdict" "$counts" "$overall" '**3 workloads**' '100.00 µs' 'Negative means faster' \
        '### Warm CLI decodes · blocking' '### Fresh CLI processes · advisory' '**1 workloads**' 'Not measured in this run'; do
        if ! grep -Fq -- "$text" .perf/results/comment.md; then
            cat .perf/results/comment.md
            echo "Missing summary text: $text"
            exit 1
        fi
    done
    test -s .perf/results/candidate-workloads.xml
    grep -Fq '### Realistic example documents' .perf/results/summary.md
    grep -Fq '### Uncategorized workloads' .perf/results/summary.md
    if grep -Eq '^### (Expected errors|Synthetic batches|Stress diagnostics|Focused diagnostics)' .perf/results/summary.md; then
        echo 'Report categories must come from groups, not benchmark or variant names'
        exit 1
    fi
    php <<'PHP'
<?php
$files = glob('.perf/results/candidate-*.xml');
$files = array_filter($files, static fn($file) => !str_contains($file, 'candidate-workloads'));
if (count($files) !== 4) {
    throw new RuntimeException('Expected four distinct workload comparisons');
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
check_run 0 unchanged '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+0.00%'
unchanged=$candidate
for entry in '50 improvement 0' '94 improvement-boundary 0' '95 at-improvement-limit 0' '104 below-limit 0' '105 at-limit 0' '106 regression 2'; do
    read -r time name expected <<< "$entry"
    printf '%s\n' "$time" > src/time.txt
    git add src/time.txt
    git commit --quiet -m "$name"
    candidate=$(git rev-parse HEAD)
    case "$name" in
        improvement) check_run "$expected" "$name" '🟢 Performance improvement' '| 2 | 1 | 0 |' '-37.00%' ;;
        improvement-boundary) check_run "$expected" "$name" '🟢 Performance improvement' '| 2 | 1 | 0 |' '-4.04%' ;;
        at-improvement-limit) check_run "$expected" "$name" '⚪ No significant performance changes' '| 0 | 3 | 0 |' '-3.36%' ;;
        below-limit) check_run "$expected" "$name" '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+2.65%' ;;
        at-limit) check_run "$expected" "$name" '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+3.31%' ;;
        regression) check_run "$expected" "$name" '🔴 Performance regression' '| 0 | 1 | 2 |' '+3.96%' ;;
    esac
done
PERFORMANCE_TEST_CONTROL_TIME=50 check_run 2 mixed '🔴 Performance regression' '| 1 | 0 | 2 |' '-17.49%'
if ! grep -Fq 'PHPBench assertions failed' regression-results/results/summary.md; then
    cat regression.log
    exit 1
fi
candidate=$unchanged PERFORMANCE_TEST_COLD_TIME=200 check_run 0 cold-slower '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+0.00%'
grep -Fq '🟡 Slower than the limit, advisory only · **Overall decode time: +100.00%** · **1 workloads**' cold-slower-results/results/comment.md
candidate=$unchanged PERFORMANCE_TEST_COLD_TIME=50 check_run 0 cold-faster '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+0.00%'
grep -Fq '🟢 Improvement · **Overall decode time: -50.00%** · **1 workloads**' cold-faster-results/results/comment.md
echo 'Native PHPBench assertions, boundaries, configuration isolation, and failure artifacts passed.'

EXPECTED_OPCACHE=0 check_run 2 opcache-off '🔴 Performance regression' '| 0 | 1 | 2 |' '+3.96%' --opcache off
php <<'PHP'
<?php
$metadata = json_decode(file_get_contents('opcache-off-results/results/metadata.json'), true, flags: JSON_THROW_ON_ERROR);
if ($metadata['opcache'] !== 'off'
    || !str_contains(file_get_contents('opcache-off-results/results/summary.md'), 'OPcache: **off**')) {
    throw new RuntimeException('Explicit OPcache-off mode was not reported');
}
PHP
status=0
EXPLICIT_WORKLOADS=1 php "$runner" --base "$baseline" --candidate "$candidate" --workloads "$candidate" > explicit.log 2>&1 || status=$?
if [ "$status" -ne 2 ]; then cat explicit.log; exit 1; fi
EXPECTED_WORKLOADS="$candidate" php <<'PHP'
<?php
$metadata = json_decode(file_get_contents('.perf/results/metadata.json'), true, flags: JSON_THROW_ON_ERROR);
if ($metadata['workloads'] !== getenv('EXPECTED_WORKLOADS') || $metadata['workloads_changed']) {
    throw new RuntimeException('Explicit workload revision was not frozen');
}
if (file_exists('.perf/results/candidate-workloads.xml')) {
    throw new RuntimeException('Explicit workloads must not be replaced by candidate workloads');
}
PHP
test "$(wc -l < .perf/preparations.txt)" -eq 2
test "$(sort -u .perf/preparations.txt | wc -l)" -eq 2
mv .perf explicit-workloads
if php "$runner" --base "$baseline" --opcache invalid > invalid.log 2>&1; then
    echo 'Expected an invalid OPcache mode to fail'
    exit 1
fi
grep -Fq 'OPcache must be on or off' invalid.log
echo 'OPcache modes and explicit frozen workloads passed.'

EXPLICIT_WORKLOADS=1 php "$runner" --base "$baseline" --candidate "$candidate" --workloads "$candidate" --shard 2 > shard.log 2>&1 && status=0 || status=$?
if [ "$status" -ne 2 ]; then cat shard.log; exit 1; fi
php <<'PHP'
<?php
$completion = json_decode(file_get_contents('.perf/results/complete.json'), true, flags: JSON_THROW_ON_ERROR);
if ($completion !== ['indices' => [1], 'status' => 2]
    || count(glob('.perf/results/candidate-*.xml')) !== 1
    || !is_file('.perf/results/baseline-1.xml') || !is_file('.perf/results/candidate-1.xml')) {
    throw new RuntimeException('Shard must measure exactly its assigned workload and retain its regression verdict');
}
foreach (['baseline', 'candidate'] as $version) {
    $xml = simplexml_load_file('.perf/results/' . $version . '-1.xml');
    if (count($xml->xpath('//variant/iteration')) !== 20) {
        throw new RuntimeException('Sharding must preserve all 20 iterations');
    }
}
PHP
mv .perf shard-results
for shard in 0 5 invalid; do
    if php "$runner" --base "$baseline" --shard "$shard" > invalid-shard.log 2>&1; then
        echo 'Invalid shards must fail'
        exit 1
    fi
done
echo 'Shard selection preserves sampling, isolated revisions, and native regression assertions.'

aggregate() {
    local name=$1 source=$2 mode=$3 fpm_time=$4 expected=$5 status=0
    shift 5
    SOURCE="$source" ARTIFACTS="$name-artifacts" FPM_TIME="$fpm_time" PROJECT="$project" MODE="$mode" php <<'PHP'
<?php
require getenv('PROJECT') . '/.github/ci/FpmMeasurements.php';
$source = getenv('SOURCE');
$metadata = json_decode(file_get_contents($source . '/metadata.json'), true, flags: JSON_THROW_ON_ERROR);
$cliStatus = json_decode(file_get_contents($source . '/complete.json'), true, flags: JSON_THROW_ON_ERROR)['status'];
for ($shard = 1; $shard <= 4; $shard++) {
    $directory = getenv('ARTIFACTS') . '/' . $shard;
    mkdir($directory, 0777, true);
    file_put_contents($directory . '/metadata.json', json_encode(array_replace($metadata, ['shard' => $shard, 'fpm' => $shard === 1]), JSON_THROW_ON_ERROR));
    copy($source . '/discovery.xml', $directory . '/discovery.xml');
    $indices = [$shard - 1];
    file_put_contents($directory . '/complete.json', json_encode(['indices' => $indices, 'status' => $shard <= 2 ? $cliStatus : 0], JSON_THROW_ON_ERROR));
    foreach ($indices as $index) {
        foreach (['baseline', 'candidate'] as $version) {
            copy($source . '/' . $version . '-' . $index . '.xml', $directory . '/' . $version . '-' . $index . '.xml');
        }
    }
}
$opcache = getenv('MODE') === 'on';
$count = Eventjet\Json\Ci\FpmMeasurements::rounds($opcache) * Eventjet\Json\Ci\FpmMeasurements::requests($opcache);
$sample = static fn(float $time): array => ['Stripe invoice' => ['time_us' => array_fill(0, $count, $time), 'peak_bytes' => 1024]];
file_put_contents(getenv('ARTIFACTS') . '/1/fpm.json', json_encode([
    'baseline' => $sample(1000.0),
    'candidate' => $sample((float) getenv('FPM_TIME')),
], JSON_THROW_ON_ERROR));
PHP
    php "$project/.github/ci/performance-aggregate.php" --artifacts "$name-artifacts" --opcache "$mode" --output "$name-report" > "$name.log" 2>&1 || status=$?
    if [ "$status" -ne "$expected" ]; then cat "$name.log"; echo "Expected status $expected, got $status for $name"; exit 1; fi
    for text in '**3 workloads**' '| Stripe invoice | 1000.00 µs |' "$@"; do
        if ! grep -Fq -- "$text" "$name-report/comment.md"; then
            cat "$name-report/comment.md"
            echo "Missing aggregate text for $name: $text"
            exit 1
        fi
    done
    grep -Fq '## Fresh PHP-FPM requests' "$name-report/summary.md"
}
aggregate cli-regression opcache-off-results/results off 1000 2 '🔴 Performance regression (OPcache off)'
EXPECTED_OPCACHE=0 candidate=$unchanged check_run 0 opcache-off-unchanged '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+0.00%' --opcache off
aggregate fpm-advisory opcache-off-unchanged-results/results off 1100 0 '⚪ No significant performance changes (OPcache off)' \
    '### First decode in a fresh PHP-FPM request · advisory' '🟡 Slower than the limit, advisory only' '+10.00%'
aggregate fpm-regression unchanged-results/results on 1100 2 '🔴 Performance regression (OPcache on)' \
    '### First decode in a fresh PHP-FPM request · blocking' '🔴 Regression · Median of' '+10.00%' \
    '### Warm CLI decodes · blocking' '### Fresh CLI processes · advisory'
aggregate fpm-improvement unchanged-results/results on 900 0 '🟢 Performance improvement (OPcache on)' '🟢 Improvement · Median of'
aggregate fpm-unchanged unchanged-results/results on 1000 0 '⚪ No significant performance changes (OPcache on)'
echo 'Aggregation preserves all samples, keeps CLI regressions, and blocks only on PHP-FPM with OPcache on.'
git rm --quiet -rf benchmarks tests phpbench.json
git commit --quiet -m 'Source without a benchmark suite'
no_benchmarks=$(git rev-parse HEAD)
SOURCE_ONLY=1 EXPLICIT_WORKLOADS=1 php "$runner" --base "$no_benchmarks" --candidate "$no_benchmarks" --workloads "$candidate" > source-only.log 2>&1
test -s .perf/results/baseline-0.xml
test -s .perf/results/candidate-0.xml
grep -Fq 'PHPBench assertions passed' .perf/results/summary.md
mv .perf source-only-results
echo 'Source-only revisions can use an independent frozen suite.'

php "$runner" --base "$baseline" --candidate "$candidate" --workloads "$no_benchmarks" > no-workloads.log 2>&1
grep -Fq 'No comparable baseline' .perf/results/summary.md
grep -Fq 'No comparable baseline' .perf/results/comment.md
mv .perf no-workload-results

git rm --quiet -r src
git commit --quiet -m 'No source directory'
no_source=$(git rev-parse HEAD)
for arguments in "--base $no_source --candidate $candidate" "--base $baseline --candidate $no_source"; do
    if php "$runner" $arguments --workloads "$candidate" > no-source.log 2>&1; then
        echo 'Compared revisions must contain source'
        exit 1
    fi
    grep -Fq 'Compared revision has no source directory' no-source.log
    rm -rf .perf
done
echo 'Missing workload suites and missing compared source are reported separately.'

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
test ! -e .perf/results/comment.md
test ! -e .perf/workspace
test -d .perf/workspace-baseline
test -d .perf/workspace-candidate
echo 'Subprocess failure diagnostics passed.'
