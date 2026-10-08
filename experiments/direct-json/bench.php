<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\Benchmark\DecodeWorkloads;
use Eventjet\Json\Benchmark\DocumentWorkloads;
use Eventjet\Json\Benchmark\Fixtures\Record;
use Eventjet\Json\Benchmark\Fixtures\RecordBatch;
use Eventjet\Json\Benchmark\Prototype\DeepNode;
use Eventjet\Json\Benchmark\Prototype\DirectParser;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\NativeJsonDecoder as Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

// One worker per measurement prevents allocator/caches from leaking across modes.
if (($argv[1] ?? '') !== '--worker') {
    $samples = (int) ($argv[1] ?? 5);
    $modes = explode(',', $argv[2] ?? 'native,pure,hybrid,compiled,regex,ordered,chunks');
    $scenarios = isset($argv[3])
        ? explode(',', $argv[3])
        : [
            'scalar object',
            'public scalar properties',
            'named enums',
            'long scalar lists',
            'long string map',
            'record batch 1000',
            'record batch 10000',
            'root records 10000',
            'recursive collections',
            ...DocumentWorkloads::names('documents'),
            'ignored tree',
            'ignored string',
        ];
    $rows = [];
    foreach ($scenarios as $scenario) {
        $measurements = [];
        for ($sample = 0; $sample < $samples; ++$sample) {
            $order = $modes;
            mt_srand(30 + $sample);
            shuffle($order);
            foreach ($order as $mode) {
                $command = [PHP_BINARY];
                if (php_ini_loaded_file() !== false) {
                    $command = [...$command, '-c', php_ini_loaded_file()];
                }
                // PHP workers do not inherit -d flags.
                foreach ([
                    'opcache.enable_cli',
                    'opcache.jit',
                    'opcache.jit_buffer_size',
                    'pcre.jit',
                    'pcre.backtrack_limit',
                    'pcre.recursion_limit',
                    'pcov.enabled',
                ] as $setting) {
                    $command[] = '-d';
                    $command[] = $setting . '=' . ini_get($setting);
                }
                $command = [...$command, __FILE__, '--worker', $mode, $scenario];
                $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                if (proc_close($process) !== 0) {
                    throw new RuntimeException($errors . $output);
                }
                $measurements[$mode][] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            }
        }
        foreach ($modes as $mode) {
            $data = $measurements[$mode];
            $median = static function (string $key) use ($data): float {
                $values = array_column($data, $key);
                sort($values);
                return $values[intdiv(count($values), 2)];
            };
            $rows[] = [
                'scenario' => $scenario,
                'mode' => $mode,
                'bytes' => $data[0]['bytes'],
                'warm_us' => $median('warm_us'),
                'cold_us' => $median('cold_us'),
                'peak_bytes' => $median('peak_bytes'),
                'cold_peak_bytes' => $median('cold_peak_bytes'),
                'retained_bytes' => $median('retained_bytes'),
                'samples' => $data,
            ];
            fprintf(
                STDERR,
                "%s | %s: %.1f us, %.0f bytes peak\n",
                $scenario,
                $mode,
                $median('warm_us'),
                $median('peak_bytes'),
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
            'samples' => $samples,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT),
        "\n";
    exit();
}

[$script, $worker, $mode, $scenario] = $argv;
if (str_ends_with($mode, '-nogc')) {
    gc_disable();
    $mode = substr($mode, 0, -5);
}
[$json, $type] = \Eventjet\Json\Benchmark\Prototype\workload($scenario);
$decode = match ($mode) {
    'native' => static fn() => Json::decode($json, $type),
    'production' => static fn() => \Eventjet\Json\Json::decode($json, $type),
    default => static fn() => DirectParser::decode($json, $type, $mode),
};
if (str_starts_with($mode, 'trusted-')) {
    // Diagnostic ceiling only: deliberately omits the syntax-before-construction guarantee.
    $decode = static fn() => DirectParser::decode($json, $type, substr($mode, 8), syntaxValidated: true);
}
if ($mode === 'native-compiled') {
    // Diagnostic best-case control: native parsing + hand-written scalar hydration.
    // Only record workloads use it; this is deliberately not a compatible decoder.
    $decode = static function () use ($json, $type) {
        $values = json_decode($json);
        $construct = static fn($row) => new Record($row->id, $row->name, $row->amount, $row->active, $row->note);
        if ($type === RecordBatch::class) {
            return new RecordBatch(array_map($construct, $values->records));
        }
        if (!is_string($type)) {
            return array_map($construct, $values);
        }
        return Json::decode($json, $type);
    };
}
gc_collect_cycles();
$before = memory_get_usage();
memory_reset_peak_usage();
$start = hrtime(true);
$result = $decode();
$cold = (hrtime(true) - $start) / 1000;
$coldPeak = memory_get_peak_usage() - $before;
if ($result instanceof DecodeError) {
    throw new RuntimeException($result->getMessage());
}
// Check semantics outside timed/peak regions; a faster incomplete result is not a win.
$expected = Json::decode($json, $type);
if (serialize($result) !== serialize($expected)) {
    throw new RuntimeException('Benchmark output differs from the native reference.');
}
unset($expected);
unset($result);
for ($index = 0; $index < 10; ++$index) {
    $decode();
}
$before = memory_get_usage();
memory_reset_peak_usage();
$result = $decode();
$peak = memory_get_peak_usage() - $before;
$retained = memory_get_usage() - $before;
unset($result);
$start = hrtime(true);
for ($index = 0; $index < 8; ++$index) {
    $decode();
}
$pilot = ((hrtime(true) - $start) / 8) / 1000;
$iterations = max(3, min(100000, (int) (100000 / max(1, $pilot))));
$start = hrtime(true);
for ($index = 0; $index < $iterations; ++$index) {
    $decode();
}
$warm = ((hrtime(true) - $start) / $iterations) / 1000;
echo
    json_encode([
        'bytes' => strlen($json),
        'cold_us' => $cold,
        'warm_us' => $warm,
        'peak_bytes' => $peak,
        'cold_peak_bytes' => $coldPeak,
        'retained_bytes' => $retained,
        'iterations' => $iterations,
        'graph_matches' => \Eventjet\Json\Benchmark\Prototype\GraphParser::$matches,
    ]),
    "\n";
