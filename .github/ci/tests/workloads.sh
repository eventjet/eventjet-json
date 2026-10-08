#!/usr/bin/env bash
set -euo pipefail

fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
cp -a benchmarks tests "$fixture/"
WORKLOAD_FIXTURE="$fixture" php <<'PHP'
<?php
require 'vendor/autoload.php';
$fixture = getenv('WORKLOAD_FIXTURE');
// Resolve workload classes from the isolated fixture so missing files cannot affect the checkout.
spl_autoload_register(static function (string $class) use ($fixture): void {
    foreach (['Eventjet\\Json\\Benchmark\\' => '/benchmarks/', 'Eventjet\\Json\\Test\\' => '/tests/'] as $prefix => $path) {
        if (str_starts_with($class, $prefix)) {
            require $fixture . $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            return;
        }
    }
}, prepend: true);
foreach (glob($fixture . '/tests/Acceptance/NorthStar/*/*.json') as $path) {
    unlink($path);
}
$names = Eventjet\Json\Benchmark\DocumentWorkloads::names();
if (count($names) !== 9 || count(array_unique($names)) !== 9) {
    throw new RuntimeException('Discovery must list all workloads without reading their documents');
}
foreach ([0, 1, 100, 1000] as $size) {
    $workload = Eventjet\Json\Benchmark\DocumentWorkloads::named('record batch ' . $size);
    $decoded = Eventjet\Json\Json::decode($workload->json, $workload->class);
    $workload->verify($decoded);
    if (count($decoded->records) !== $size) {
        throw new RuntimeException('Selected the wrong record batch');
    }
}
$document = '/tests/Acceptance/NorthStar/Kubernetes/deployment.json';
copy(getcwd() . $document, $fixture . $document);
$workload = Eventjet\Json\Benchmark\DocumentWorkloads::named('Kubernetes deployment');
$workload->verify(Eventjet\Json\Json::decode($workload->json, $workload->class));
try {
    Eventjet\Json\Benchmark\DocumentWorkloads::named('missing');
    throw new RuntimeException('Unknown workload names must be rejected');
} catch (InvalidArgumentException) {
}
echo "Workload discovery and selection are independent of unrelated fixtures.\n";
PHP
