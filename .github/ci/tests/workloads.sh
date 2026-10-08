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

for group in documents batches diagnostic stress errors; do
    vendor/bin/phpbench run --group="$group" --iterations=1 --revs=1 --progress=none --dump-file="$fixture/$group.xml"
done
WORKLOAD_FIXTURE="$fixture" php <<'PHP'
<?php
require 'vendor/autoload.php';
$expected = [
    'documents' => array_keys(Eventjet\Json\Test\Acceptance\Cases\SupportedDocumentRoundTripCases::factories()),
    'batches' => ['record batch 0', 'record batch 1', 'record batch 100', 'record batch 1000'],
    'diagnostic' => ['scalar object', 'public scalar properties', 'scalar lists', 'long scalar lists', 'long string map', 'named enums', 'enum union'],
    'stress' => ['enum union collections', 'enum-heavy object collections', 'recursive collections', 'enum-heavy root array'],
    'errors' => ['unknown enum', 'first list item', 'last list item'],
];
foreach ($expected as $category => $names) {
    $xml = simplexml_load_file(getenv('WORKLOAD_FIXTURE') . '/' . $category . '.xml');
    $actual = [];
    foreach ($xml->xpath('//subject') as $subject) {
        $groups = array_map(static fn($group) => (string) $group['name'], iterator_to_array($subject->group, false));
        if (array_values(array_intersect($groups, array_keys($expected))) !== [$category]) {
            throw new RuntimeException('Each subject must have exactly its selected report category');
        }
        foreach ($subject->variant as $variant) {
            $name = (string) $variant->{'parameter-set'}['name'];
            $actual[$name] = ($actual[$name] ?? 0) + 1;
        }
    }
    $counts = array_fill_keys($names, $category === 'errors' ? 1 : 2);
    ksort($actual);
    ksort($counts);
    if ($actual !== $counts) {
        throw new RuntimeException('Local group selection differs from the expected report category: ' . $category);
    }
}
echo "Local groups and report categories cover the same workloads, including cold and warm cases.\n";
PHP
