<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Prototype;

use Eventjet\Json\Benchmark\DecodeWorkloads;
use Eventjet\Json\Benchmark\DocumentWorkloads;
use Eventjet\Json\Benchmark\Fixtures\Record;
use Eventjet\Json\Benchmark\Fixtures\RecordBatch;
use Eventjet\Json\Benchmark\Prototype\DeepNode;
use Eventjet\Json\Benchmark\Prototype\DirectParser;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

/** Shared fixtures keep isolated and paired measurements identical. */
function workload(string $scenario): array
{
    if (str_starts_with($scenario, 'deep chain ')) {
        $node = null;
        for ($index = (int) substr($scenario, 11); $index >= 0; --$index) {
            $node = new DeepNode($index, $node);
        }
        $type = DeepNode::class;
        $json = json_encode($node, JSON_THROW_ON_ERROR);
        unset($node);
    } elseif ($scenario === 'large string field') {
        $type = ScalarFields::class;
        $json = json_encode(new ScalarFields(str_repeat('x', 4 * 1024 * 1024), 42, 1.5, true), JSON_THROW_ON_ERROR);
    } elseif (str_starts_with($scenario, 'record batch ') || $scenario === 'root records 10000') {
        $size = $scenario === 'root records 10000' ? 10000 : (int) substr($scenario, 13);
        $records = [];
        for ($index = 0; $index < $size; ++$index) {
            $records[] = new Record(
                $index,
                'record-' . $index,
                ($index / 4) + 0.5,
                (bool) ($index % 2),
                $index % 3 ? 'note-' . $index : null,
            );
        }
        $type = $scenario === 'root records 10000' ? JsonType::array(Record::class) : RecordBatch::class;
        $json = json_encode(is_string($type) ? new RecordBatch($records) : $records, JSON_THROW_ON_ERROR);
        unset($records);
    } elseif (in_array($scenario, DocumentWorkloads::names('documents'), true)) {
        $workload = DocumentWorkloads::named($scenario);
        $json = $workload->json;
        $type = $workload->class;
        unset($workload);
    } else {
        $object = DecodeWorkloads::create(str_starts_with($scenario, 'ignored ') ? 'scalar object' : $scenario);
        $type = $object::class;
        $json = json_encode($object, JSON_THROW_ON_ERROR);
        unset($object);
        if ($scenario === 'ignored tree') {
            $json =
                substr($json, 0, -1)
                . ',"ignored":['
                . str_repeat('{"x":[1,2,3],"text":"ignore me"},', 49999)
                . '{"x":[]}]}';
        } elseif ($scenario === 'ignored string') {
            $json = substr($json, 0, -1) . ',"ignored":"' . str_repeat('x', 4 * 1024 * 1024) . '"}';
        }
    }
    return [$json, $type];
}
