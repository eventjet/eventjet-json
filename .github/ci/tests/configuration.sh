#!/usr/bin/env bash
set -euo pipefail

# Exercise the real runner with deterministic PHPBench output, without timing noise.
runner="$(cd "$(dirname "$0")/.." && pwd)/performance.php"
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
unset GIT_DIR GIT_WORK_TREE
cd "$fixture"
git init --quiet
git config user.name 'Performance configuration test'
git config user.email 'performance-test@example.invalid'
mkdir -p src benchmarks tests vendor/bin vendor/composer
touch src/placeholder benchmarks/placeholder tests/placeholder
printf '%s\n' '{"name":"test/performance","autoload":{}}' > composer.json
printf '%s\n' '{"packages":[]}' > vendor/composer/installed.json
printf '%s\n' '{"runner.path":"baseline"}' > phpbench.json
git add src benchmarks tests composer.json phpbench.json
git commit --quiet -m baseline
baseline=$(git rev-parse HEAD)
printf '%s\n' '{"runner.path":"candidate"}' > phpbench.json
git add phpbench.json
git commit --quiet -m candidate
candidate=$(git rev-parse HEAD)
# Neither measured revision should use the current working tree's configuration.
printf '%s\n' '{"runner.path":"uncommitted"}' > phpbench.json

cat > vendor/bin/phpbench <<'PHP'
<?php
declare(strict_types=1);
$config = json_decode(file_get_contents('phpbench.json'), true, flags: JSON_THROW_ON_ERROR);
$path = $config['runner.path'];
if (!in_array($path, ['baseline', 'candidate'], true)) {
    throw new RuntimeException('Configuration did not come from a measured revision');
}
foreach (['pcov.enabled', 'opcache.enable_cli', 'opcache.jit'] as $setting) {
    if ($config['runner.php_config'][$setting] !== '0') {
        throw new RuntimeException('Controlled PHP settings were lost');
    }
}
$dumpFile = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--dump-file=')) {
        $dumpFile = substr($argument, strlen('--dump-file='));
    }
}
if ($dumpFile === null) {
    throw new RuntimeException('No dump file was requested');
}
$iterations = str_repeat('<iteration time-net="100" mem-peak="1024"/>', 5);
file_put_contents($dumpFile, '<phpbench><suite><benchmark class="Decoder">'
    . '<subject name="' . $path . '"><variant revs="10"><parameter-set name="case"/>'
    . $iterations . '</variant></subject></benchmark></suite></phpbench>');
PHP

if ! php "$runner" --base "$baseline" --candidate "$candidate" > runner.log 2>&1; then
    cat runner.log
    exit 1
fi
php <<'PHP'
<?php
declare(strict_types=1);
$results = json_decode(file_get_contents('.perf/results/results.json'), true, flags: JSON_THROW_ON_ERROR);
foreach (['calibration', 'comparison'] as $experiment) {
    if (array_keys($results[$experiment]) !== ['Decoder / baseline / case']) {
        throw new RuntimeException('The comparison did not freeze baseline configuration');
    }
}
$xml = file_get_contents('.perf/results/candidate-workloads.xml');
if (!str_contains($xml, 'name="candidate"') || str_contains($xml, 'name="baseline"')) {
    throw new RuntimeException('The separate candidate run did not use candidate configuration');
}
echo "Baseline and candidate configuration isolation passed.\n";
PHP
