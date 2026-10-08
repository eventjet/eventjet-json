<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\Benchmark\Prototype\DirectParser;
use Eventjet\Json\Benchmark\Prototype\GraphParser;

use function Eventjet\Json\Benchmark\Prototype\workload;

// Diagnostic phase attribution only. Construction by itself is not a compatible
// decoder: it assumes the typed grammar has already accepted this exact input.
$mode = $argv[1] ?? 'graph-flex-direct-bulk';
$scenarios = explode(
    ',',
    $argv[2] ?? 'Stripe invoice,GitHub pull request webhook,Kubernetes deployment,record batch 1000,large string field',
);
$cache = new ReflectionProperty(GraphParser::class, 'cache');
$rows = [];
foreach ($scenarios as $scenario) {
    [$json, $type] = workload($scenario);
    DirectParser::decode($json, $type, $mode);
    $compiled = $cache->getValue()[$mode . ':' . (is_string($type) ? $type : serialize($type))];
    if ($compiled === false || preg_match($compiled[0], $json) !== 1) {
        continue;
    }
    $functions = [
        'native-validation' => static fn() => json_validate($json),
        'typed-validation' => static fn() => preg_match($compiled[0], $json),
        'construction-only' => static fn() => $compiled[1]($json),
        'complete' => static fn() => DirectParser::decode($json, $type, $mode),
    ];
    $times = [];
    foreach ($functions as $phase => $function) {
        $start = hrtime(true);
        for ($index = 0; $index < 10; ++$index) {
            $function();
        }
        $pilot = (hrtime(true) - $start) / 10000;
        $count = max(3, min(100000, (int) (100000 / max($pilot, 1))));
        $samples = [];
        for ($sample = 0; $sample < 5; ++$sample) {
            $start = hrtime(true);
            for ($index = 0; $index < $count; ++$index) {
                $function();
            }
            $samples[] = (hrtime(true) - $start) / ($count * 1000);
        }
        sort($samples);
        $times[$phase] = ['median_us' => $samples[2], 'min_us' => min($samples), 'max_us' => max($samples)];
    }
    $rows[] = ['scenario' => $scenario, 'mode' => $mode, 'phases' => $times];
    fprintf(
        STDERR,
        "%s: validate %.1f us, construct %.1f us, total %.1f us\n",
        $scenario,
        $times['typed-validation']['median_us'],
        $times['construction-only']['median_us'],
        $times['complete']['median_us'],
    );
}
echo json_encode(['php' => PHP_VERSION, 'rows' => $rows], JSON_PRETTY_PRINT), "\n";
