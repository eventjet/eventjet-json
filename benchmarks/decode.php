<?php

declare(strict_types=1);

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;

require dirname(__DIR__) . '/vendor/autoload.php';

$scalar = '{"string":"hello","integer":42,"float":1.5,"boolean":true}';
$enums = '{"stringStatus":"ready","nullableStatus":null,"intStatus":1}';
$combined =
    '{"nested":'
    . $enums
    . ',"items":['
    . implode(',', array_fill(0, 20, $enums))
    . '],"named":{"first":'
    . $enums
    . '},"publicMap":{"second":'
    . $enums
    . '}}';
$recursive = '{"lists":[],"maps":{}}';
for ($depth = 0; $depth < 4; ++$depth) {
    $recursive = '{"lists":[[' . $recursive . ']],"maps":{"branch":{"leaf":' . $recursive . '}}}';
}
$cases = [
    'scalar object' => [$scalar, ScalarFields::class],
    'scalar lists' => [
        '{"strings":["a","b"],"stringsExtra":[1,2],"floats":[1.5,2.5],"booleans":[true,false]}',
        ScalarListFields::class,
    ],
    'object collections' => [$combined, CombinedCollectionFields::class],
    'recursive collections' => [$recursive, NestedSelfCollections::class],
    'root array (100 objects)' => [
        '[' . implode(',', array_fill(0, 100, $combined)) . ']',
        JsonType::array(CombinedCollectionFields::class),
    ],
];

printf(
    "PHP %s; median of 5 samples; pcov=%s; CLI opcache=%s\n",
    PHP_VERSION,
    ini_get('pcov.enabled'),
    ini_get('opcache.enable_cli'),
);
foreach ($cases as $name => [$json, $target]) {
    $start = hrtime(true);
    $expected = Json::decode($json, $target);
    $cold = (hrtime(true) - $start) / 1e6;
    if ($expected instanceof DecodeError) {
        throw $expected;
    }
    $canonical = json_encode($expected, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $iterations = str_starts_with($name, 'root array') ? 5 : 200;
    $samples = [];
    for ($sample = 0; $sample < 5; ++$sample) {
        $start = hrtime(true);
        for ($iteration = 0; $iteration < $iterations; ++$iteration) {
            $decoded = Json::decode($json, $target);
            if ($decoded instanceof DecodeError) {
                throw $decoded;
            }
        }
        $samples[] = ((hrtime(true) - $start) / $iterations) / 1e6;
        if (json_encode($decoded, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) !== $canonical) {
            throw new RuntimeException('Repeated decoding changed the result.');
        }
    }
    sort($samples);
    printf("%-25s first: %8.3f ms; warm: %8.3f ms/decode\n", $name, $cold, $samples[2]);
}
printf("Peak allocated memory: %.1f MiB\n", memory_get_peak_usage(true) / 1048576);
