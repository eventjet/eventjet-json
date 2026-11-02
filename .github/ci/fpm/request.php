<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci\Fpm;

use Eventjet\Json\Benchmark\DocumentWorkloads;
use Eventjet\Json\Ci\FpmRuntime;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use RuntimeException;

require dirname(__DIR__) . '/FpmRuntime.php';

$workspace = getenv('BENCHMARK_WORKSPACE');
if ($workspace === false || $workspace === '') {
    throw new RuntimeException('Missing benchmark workspace.');
}
require $workspace . '/vendor/autoload.php';

if (PHP_SAPI !== 'fpm-fcgi') {
    throw new RuntimeException('This benchmark requires PHP-FPM.');
}
$name = getenv('QUERY_STRING');
if ($name === false) {
    throw new RuntimeException('Missing workload query string.');
}
$workload = DocumentWorkloads::named(rawurldecode($name));
$before = FpmRuntime::status();
$start = hrtime(true);
$decoded = Json::decode($workload->json, $workload->class);
$elapsed = (hrtime(true) - $start) / 1000;
$peak = memory_get_peak_usage(true);
$after = FpmRuntime::status();
if ($decoded instanceof DecodeError) {
    throw $decoded;
}
$workload->verify($decoded);
header('Content-Type: application/json');
echo
    json_encode([
        'time_us' => $elapsed,
        'peak_bytes' => $peak,
        'php_version' => PHP_VERSION,
        'opcache_enabled' => $after['enabled'],
        'opcache_misses' => $after['misses'] - $before['misses'],
        'jit_enabled' => $after['jit'],
        'pid' => getmypid(),
        'input_bytes' => strlen($workload->json),
    ], JSON_THROW_ON_ERROR);
