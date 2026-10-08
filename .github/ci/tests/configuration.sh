#!/usr/bin/env bash
set -euo pipefail

# Use PHPBench's deterministic executor to test the real assertion and exit status.
project=$(cd "$(dirname "$0")/../../.." && pwd)
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
foreach (['pcov.enabled', 'opcache.enable_cli', 'opcache.jit'] as $setting) {
    if ($config['runner.php_config'][$setting] !== '0') {
        throw new RuntimeException('Controlled PHP settings were lost');
    }
}
$time = (int) trim(file_get_contents('src/time.txt'));
foreach ($argv as $argument) {
    if (str_contains($argument, 'benchControl')) {
        $time = $time === 100 ? 100 : (int) (getenv('PERFORMANCE_TEST_CONTROL_TIME') ?: 100);
    }
}
$arguments = [PHP_BINARY, getenv('PERFORMANCE_TEST_PHPBENCH'), ...array_slice($argv, 1),
    '--executor=' . json_encode(['executor' => 'debug', 'times' => [$time]])];
passthru(implode(' ', array_map(escapeshellarg(...), $arguments)), $status);
exit($status);
PHP

check_run() {
    local expected=$1 name=$2 verdict=$3 counts=$4 overall=$5 status=0
    php "$runner" --base "$baseline" --candidate "$candidate" > "$name.log" 2>&1 || status=$?
    if [ "$status" -ne "$expected" ]; then
        cat "$name.log"
        echo "Expected status $expected, got $status for $name"
        exit 1
    fi
    test -s .perf/results/baseline-0.xml
    test -s .perf/results/candidate-0.xml
    test -s .perf/results/candidate-0.txt
    test -s .perf/results/summary.md
    test -s .perf/results/comment.md
    for text in "$verdict" "$counts" "$overall" '**3 workloads**' '100.00 µs' 'Negative means faster'; do
        if ! grep -Fq -- "$text" .perf/results/comment.md; then
            cat .perf/results/comment.md
            echo "Missing summary text: $text"
            exit 1
        fi
    done
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
check_run 0 unchanged '⚪ No significant performance changes' '| 0 | 3 | 0 |' '+0.00%'
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
echo 'Native PHPBench assertions, boundaries, configuration isolation, and failure artifacts passed.'

git rm --quiet -r benchmarks
git commit --quiet -m 'No baseline benchmarks'
php "$runner" --base HEAD --candidate "$candidate" > no-baseline.log 2>&1
grep -Fq 'No comparable baseline' .perf/results/comment.md
mv .perf no-baseline-results

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
echo 'Subprocess failure diagnostics passed.'
