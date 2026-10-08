<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use FieldPrototype\Mapped;
use FieldPrototype\Plain;

$baseline = getenv('FIELD_PROTOTYPE_BASELINE') === '1';
$mapped = in_array('--mapped', $argv, true);
$class = $mapped ? Mapped::class : Plain::class;
$json = $mapped ? '{"uri":"example","total":42,"valid":true}' : '{"ref":"example","count":42,"ready":true}';
$start = hrtime(true);
$result = Json::decode($json, $class);
$cold = hrtime(true) - $start;
if ($result instanceof DecodeError) {
    throw $result;
}
$original = new $class('example', 42, true);
if ($result != $original || json_encode($result, JSON_THROW_ON_ERROR) !== $json) {
    throw new RuntimeException('Unexpected benchmark result.');
}
$decode = [];
$encode = [];
for ($sample = 0; $sample < 15; ++$sample) {
    $start = hrtime(true);
    for ($iteration = 0; $iteration < 100_000; ++$iteration) {
        $result = Json::decode($json, $class);
    }
    $decode[] = (hrtime(true) - $start) / 100_000;
    $start = hrtime(true);
    for ($iteration = 0; $iteration < 100_000; ++$iteration) {
        $encoded = json_encode($original, JSON_THROW_ON_ERROR);
    }
    $encode[] = (hrtime(true) - $start) / 100_000;
}
sort($decode);
sort($encode);
echo json_encode([
    'runtime' => PHP_VERSION,
    'opcache' => ini_get('opcache.enable_cli'),
    'baseline' => $baseline,
    'mapped' => $mapped,
    'first_decode_ns' => $cold,
    'warm_decode_median_ns' => $decode[7],
    'warm_encode_median_ns' => $encode[7],
    'decode_samples_ns' => $decode,
    'encode_samples_ns' => $encode,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
