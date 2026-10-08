<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\Benchmark\Prototype\DirectParser;
use Eventjet\Json\Benchmark\Prototype\NativeJsonDecoder as Json;

use function Eventjet\Json\Benchmark\Prototype\workload;

// Time-only corroboration: randomized blocks compare fully warmed modes in one
// process. Peak/cold measurements remain in bench.php's isolated workers.
$rounds = (int) ($argv[1] ?? 15);
$modes = explode(',', $argv[2] ?? 'native,window-8192,extreme');
if ($rounds < 1 || !in_array('native', $modes, true)) {
    throw new InvalidArgumentException('Use at least one round and include the native baseline.');
}
$scenarios = explode(
    ',',
    $argv[3]
    ?? 'scalar object,record batch 1000,deep chain 384,GitHub pull request webhook,Stripe invoice,Kubernetes deployment,long scalar lists,ignored tree,ignored string',
);
$rows = [];
foreach ($scenarios as $scenario) {
    [$json, $type] = workload($scenario);
    $functions = [];
    $iterations = [];
    $samples = [];
    $expected = serialize(Json::decode($json, $type));
    foreach ($modes as $mode) {
        $function = match ($mode) {
            'native' => static fn() => Json::decode($json, $type),
            'production' => static fn() => \Eventjet\Json\Json::decode($json, $type),
            default => static fn() => DirectParser::decode($json, $type, $mode),
        };
        if (serialize($function()) !== $expected) {
            throw new RuntimeException('Output differs: ' . $scenario . ' / ' . $mode);
        }
        for ($index = 0; $index < 20; ++$index) {
            $function();
        }
        $start = hrtime(true);
        for ($index = 0; $index < 8; ++$index) {
            $function();
        }
        $pilot = (hrtime(true) - $start) / 8000;
        $iterations[$mode] = max(1, min(100000, (int) (25000 / max(1, $pilot))));
        $functions[$mode] = $function;
        $samples[$mode] = [];
    }
    for ($round = 0; $round < $rounds; ++$round) {
        $order = $modes;
        mt_srand(839 + $round);
        shuffle($order);
        foreach ($order as $mode) {
            $function = $functions[$mode];
            $count = $iterations[$mode];
            $start = hrtime(true);
            for ($index = 0; $index < $count; ++$index) {
                $function();
            }
            $samples[$mode][] = (hrtime(true) - $start) / ($count * 1000);
        }
    }
    foreach ($modes as $mode) {
        $times = $samples[$mode];
        sort($times);
        $ratios = [];
        foreach ($samples[$mode] as $round => $time) {
            $ratios[] = $time / $samples['native'][$round];
        }
        sort($ratios);
        $row = [
            'scenario' => $scenario,
            'mode' => $mode,
            'median_us' => $times[intdiv($rounds, 2)],
            'median_paired_ratio' => $ratios[intdiv($rounds, 2)],
            'min_us' => min($times),
            'max_us' => max($times),
            'iterations' => $iterations[$mode],
            'samples_us' => $samples[$mode],
        ];
        $rows[] = $row;
        fprintf(
            STDERR,
            "%s | %s: %.1f us, ratio %.3f\n",
            $scenario,
            $mode,
            $row['median_us'],
            $row['median_paired_ratio'],
        );
    }
}
echo
    json_encode([
        'php' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'opcache' => ini_get('opcache.enable_cli'),
        'jit' => ini_get('opcache.jit'),
        'pcre_jit' => ini_get('pcre.jit'),
        'rounds' => $rounds,
        'rows' => $rows,
    ], JSON_PRETTY_PRINT),
    "\n";
