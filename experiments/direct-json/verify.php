<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\Benchmark\Fixtures\Record;
use Eventjet\Json\Benchmark\Fixtures\RecordBatch;
use Eventjet\Json\Benchmark\Prototype\DirectParser;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Cases;

$count = 0;
$failures = [];
$modes = [
    'graph',
    'graph-compact',
    'graph-flex',
    'graph-compact-flex',
    'columns-4096',
    'columns-8192',
    'columns-32768',
    'fused',
    'projection',
    'graph-compact-inline',
    'graph-compact-inline-bulk',
    'graph-flex-bulk',
    'extreme',
    'graph-flex-registers-bulk',
    'graph-flex-direct-bulk',
    'graph-flex-direct-bulk-trie',
    'graph-compact-inline-bulk-loop',
    'fused-batch',
    'projection-ascii',
    'graph-compact-inline-bulk-loop-ascii',
    'graph-flex-inline-direct-bulk',
    'graph-flex-inline-direct-bulk-guard',
    'graph-flex-inline-direct-bulk-header',
    'pure',
    'hybrid',
    'native-strings',
    'compiled',
    'regex',
    'ordered',
    'chunks',
    'chunks-16',
    'chunks-1024',
    'packed-64',
    'window-8192',
    'window-32768',
    'validate-window-8192',
    'lazy-window-8192',
    'validate-lazy-window-8192',
    'indexed-lazy-window-8192',
    'validate-indexed-lazy-window-8192',
];
$compare = static function (string $name, string $json, string|JsonType $type) use (&$count, &$failures, $modes): void {
    $expected = Json::decode($json, $type);
    foreach ($modes as $mode) {
        ++$count;
        $actual = DirectParser::decode($json, $type, $mode);
        if ($expected instanceof DecodeError) {
            $same =
                $actual instanceof DecodeError
                && $expected->getCode() === $actual->getCode()
                && $expected->getMessage() === $actual->getMessage();
            if ($same && $expected->getPrevious() !== null) {
                $message = static fn(Throwable $error) => preg_replace(
                    '/ passed in .+ on line \d+ and /',
                    ' passed and ',
                    $error->getMessage(),
                );
                $same =
                    $actual->getPrevious() !== null
                    && $actual->getPrevious()::class === $expected->getPrevious()::class
                    && $message($actual->getPrevious()) === $message($expected->getPrevious());
            }
        } else {
            $same = $actual == $expected && serialize($actual) === serialize($expected);
        }
        if (!$same) {
            $failures[] = [
                $name,
                $mode,
                $expected instanceof DecodeError ? $expected->getMessage() : serialize($expected),
                $actual instanceof DecodeError ? $actual->getMessage() : serialize($actual),
            ];
        }
    }
};

foreach (Cases\ObjectRoundTripCases::objects() as $name => [$object]) {
    $compare($name, json_encode($object, JSON_THROW_ON_ERROR), $object::class);
}
foreach (Cases\RootCollectionRoundTripCases::objects() as $name => [$object, $factory]) {
    $compare($name, json_encode($object, JSON_THROW_ON_ERROR), $factory());
}
foreach ([Cases\JsonFormattingRoundTripCases::class, Cases\EmptyShapeRoundTripCases::class] as $provider) {
    foreach ($provider::objects() as $name => [$object, $json]) {
        $compare($name, $json, $object::class);
    }
}
foreach ([Cases\ConstructorDefaultCases::class, Cases\UnknownFieldCases::class] as $provider) {
    foreach ($provider::objects() as $name => [$json, $object]) {
        $compare($name, $json, $object::class);
    }
}
foreach (Cases\SupportedDocumentRoundTripCases::objects() as $name => [$json, $type]) {
    $compare($name, $json, $type);
}
foreach (Cases\DecodeErrorCases::errors() as $name => [$json, $type]) {
    $compare($name, $json, $type);
}
foreach (Cases\DecodeErrorCases::errors() as $name => $case) {
    if (!isset($case[4])) {
        continue;
    }
    foreach ($modes as $mode) {
        ++$count;
        $error = DirectParser::decode($case[0], $case[1], $mode);
        if (!$error instanceof DecodeError || $error->getPrevious() !== $case[4]) {
            $failures[] = ['underlying-exception-identity', $name, $mode];
        }
    }
}
foreach (Cases\NumericObjectKeyCases::mismatches() as $name => [$json, $type]) {
    $compare($name, $json, $type);
}

// Seeded differential inputs include shuffled fields, ignored trees, escapes and
// numeric boundaries. The oracle is the current decoder, not a second parser.
mt_srand(3008);
$strings = ['', 'plain', "\0\x01\x1f\n\r\t\"\\/", 'ä€😀', str_repeat('long', 1024)];
for ($iteration = 0; $iteration < 500; ++$iteration) {
    $object = new Record(
        mt_rand(-100000, 100000),
        $strings[$iteration % count($strings)],
        mt_rand(-100000, 100000) / 13,
        (bool) ($iteration % 2),
        $iteration % 3 ? 'note' : null,
    );
    $values = get_object_vars($object);
    $values['ignored'] = ['tree' => [['escaped' => $strings[$iteration % count($strings)]]]];
    $keys = array_keys($values);
    shuffle($keys);
    $shuffled = [];
    foreach ($keys as $key) {
        $shuffled[$key] = $values[$key];
    }
    $flags = JSON_THROW_ON_ERROR | ($iteration % 2 ? JSON_PRETTY_PRINT : JSON_UNESCAPED_UNICODE);
    $json = json_encode($shuffled, $flags);
    if ($iteration < 100) {
        $compare('canonical-generated-' . $iteration, json_encode($object, $flags), Record::class);
    }
    $compare('generated-' . $iteration, $json, Record::class);
    if ($iteration < 100) {
        $compare('trailing-' . $iteration, $json . 'x', Record::class);
        $compare('truncated-' . $iteration, substr($json, 0, mt_rand(0, strlen($json) - 1)), Record::class);
    }
}
foreach ([
    '-0',
    '-0.0',
    '1e400',
    '-1e-400',
    '9223372036854775808',
    '-9223372036854775809',
    '9007199254740993',
    '0.000000000000001',
] as $number) {
    $compare(
        'number-' . $number,
        '{"id":1,"name":"n","amount":' . $number . ',"active":true,"note":null}',
        Record::class,
    );
}
$records = [];
for ($index = 0; $index < 1000; ++$index) {
    $records[] = new Record($index, 'record-' . $index, $index / 4, (bool) ($index % 2), null);
}
$compare('chunked-batch', json_encode(new RecordBatch($records)), RecordBatch::class);
$compare('chunked-root', json_encode($records), JsonType::array(Record::class));
$values = array_map('get_object_vars', $records);
$values[150]['ignored'] = [1, ['x' => 'skip']];
$compare('chunked-unknown', json_encode($values), JsonType::array(Record::class));
$values[250]['id'] = 1.5;
$compare('chunked-invalid-integer', json_encode($values), JsonType::array(Record::class));
$long = new Record(1, str_repeat('x', 70000), 1.5, true, null);
$compare('large-known-string', json_encode($long), Record::class);
$compare(
    'large-known-string-list',
    json_encode([$long, ...array_slice($records, 0, 150)]),
    JsonType::array(Record::class),
);
foreach ([
    '"\\u0000"',
    '"\\ud83d\\ude00"',
    '"\\u0061\\u0062\\ud83d\\ude00"',
    '"\\\\u0061"',
    '"\\ud800"',
    '"\\udc00"',
    '"\\uZZZZ"',
    '"' . "\xff" . '"',
] as $string) {
    $compare(
        'escape-' . bin2hex($string),
        '{"id":1,"name":' . $string . ',"amount":1,"active":true,"note":null}',
        Record::class,
    );
}
foreach ([
    '"\\u0000x":1',
    '"ignored":{"\\u0000":1}',
    '"ignored":"\\\"\\u0000x\\\":1"',
    '"ignored":"\\u0000"',
    '"x\\u0000":1',
] as $member) {
    $json = '{"id":1,"name":"n","amount":1,"active":true,"note":null,' . $member . '}';
    $compare('property-' . $member, $json, Record::class);
    $compare('property-trailing-' . $member, $json . 'x', Record::class);
}
for ($depth = 508; $depth <= 514; ++$depth) {
    $json =
        '{"id":1,"name":"n","amount":1,"active":true,"note":null,"ignored":'
        . str_repeat('[', $depth)
        . 'null'
        . str_repeat(']', $depth)
        . '}';
    $compare('depth-' . $depth, $json, Record::class);
}
class_alias(Record::class, 'PrototypeRecordAlias');
$compare('class-alias', '{"id":1,"name":"n","amount":1,"active":true,"note":null}', 'PrototypeRecordAlias');
$compare('class-alias-error', '{"id":"wrong","name":"n","amount":1,"active":true,"note":null}', 'PrototypeRecordAlias');
// Independent grammar fuzzing checks the validator directly against native JSON
// decoding, including malformed bytes and Unicode surrogate sequences.
$atoms = ['null', 'true', 'false', '0', '-0', '0.1', '1e9', '"x"', '"\\u0061"', '"\\ud83d\\ude00"', '[]', '{}'];
for ($iteration = 0; $iteration < 3000; ++$iteration) {
    $atom = $atoms[$iteration % count($atoms)];
    $json = $iteration % 2 ? '[ ' . $atom . ',' . $atom . ' ]' : '{ "x":' . $atom . ', "y":[' . $atom . ']}';
    if (($iteration % 3) !== 0) {
        $position = mt_rand(0, strlen($json) - 1);
        $json = substr_replace($json, chr(mt_rand(0, 255)), $position, $iteration % 5 ? 1 : 0);
    }
    $nativeValid = json_validate($json);
    $customValid = Eventjet\Json\Benchmark\Prototype\RegexValidator::validate($json);
    ++$count;
    if ($nativeValid !== $customValid) {
        $failures[] = ['validator-fuzz', bin2hex($json), $nativeValid, $customValid];
    }
}

final class ProbeChild
{
    public static int $calls = 0;

    public function __construct(
        public int $id,
    ) {
        ++self::$calls;
    }
}

final class ProbeParent
{
    public function __construct(
        public ProbeChild $child,
        public int $id,
    ) {}
}

final class PropertyHookOrder
{
    public static array $calls = [];

    public int $first {
        set {
            self::$calls[] = 'first';
            $this->first = $value;
        }
    }

    public int $second {
        set {
            self::$calls[] = 'second';
            $this->second = $value;
        }
    }
}

foreach ($modes as $mode) {
    foreach (['{"first":1,"second":2}', '{"second":2,"first":1}'] as $json) {
        PropertyHookOrder::$calls = [];
        $expected = Json::decode($json, PropertyHookOrder::class);
        $calls = PropertyHookOrder::$calls;
        PropertyHookOrder::$calls = [];
        $actual = DirectParser::decode($json, PropertyHookOrder::class, $mode);
        ++$count;
        if ($calls !== PropertyHookOrder::$calls || $actual != $expected) {
            $failures[] = ['public-property-hook-order', $mode, $json];
        }
    }
    foreach ([
        '{"child":{"id":1},"id":2,"ignored":[1,]}',
        '{"child":{"id":1},"id":2} trailing',
        '{"child":{"id":1},"id":"wrong","ignored":[1,]}',
    ] as $json) {
        ProbeChild::$calls = 0;
        $result = DirectParser::decode($json, ProbeParent::class, $mode);
        ++$count;
        if (!$result instanceof DecodeError || $result->getCode() !== 1 || ProbeChild::$calls !== 0) {
            $failures[] = ['syntax-before-construction', $mode];
        }
    }
    foreach ([
        '{"child":{"id":1},"id":"wrong"}',
        '{"id":"wrong","child":{"id":1}}',
        '{"child":{"id":1},"id":2}',
    ] as $json) {
        ProbeChild::$calls = 0;
        $expected = Json::decode($json, ProbeParent::class);
        $calls = ProbeChild::$calls;
        ProbeChild::$calls = 0;
        $actual = DirectParser::decode($json, ProbeParent::class, $mode);
        ++$count;
        if (ProbeChild::$calls !== $calls || $actual instanceof DecodeError !== $expected instanceof DecodeError) {
            $failures[] = ['validation-construction-order', $mode, $json];
        }
    }
}

final class GraphTraceChild
{
    public static array $calls = [];

    public function __construct(
        public int $id,
    ) {
        self::$calls[] = $id;
        if ($id < 0) {
            throw new RuntimeException('negative trace child');
        }
    }
}

final class NarrowSetFields
{
    public private(set) int $id = 0;
    public protected(set) string $name = '';
}

final class LinkedFields
{
    public int $x = 0;
    public int $y = 0;

    public function __construct()
    {
        $this->y = &$this->x;
    }
}

final class MagicSetField
{
    public int $id = 0;

    public function __construct()
    {
        unset($this->id);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->{$name} = $value + 1;
    }
}

$compare('narrow-set-visibility', '{"id":3,"name":"n"}', NarrowSetFields::class);
$compare('narrow-set-visibility-reversed', '{"name":"n","id":3}', NarrowSetFields::class);
$compare('linked-public-fields', '{"x":1,"y":2}', LinkedFields::class);
$compare('linked-public-fields-reversed', '{"y":2,"x":1}', LinkedFields::class);
$compare('magic-public-field', '{"id":3}', MagicSetField::class);
$compare('missing-class-valid-json', '{}', 'MissingPrototypeTarget');
$compare('missing-class-invalid-json', '{', 'MissingPrototypeTarget');

$lists = \Eventjet\Json\Benchmark\DecodeWorkloads::create('long scalar lists');
$listValues = json_decode(json_encode($lists), true);
foreach ($listValues as &$list) {
    if ($list !== [] && is_string($list[0])) {
        $list[0] = 'a]b}c';
    }
}
unset($list);
$compare('bulk-string-delimiters', json_encode($listValues), $lists::class);

final class GraphTraceParent
{
    public GraphTraceChild|null $extra = null;

    public function __construct(
        public GraphTraceChild $required,
        public GraphTraceChild|null $first = null,
        public GraphTraceChild|null $second = null,
        public GraphTraceChild $default = new GraphTraceChild(7),
    ) {
        GraphTraceChild::$calls[] = 99;
    }
}

foreach ($modes as $mode) {
    foreach (range(0, 5) as $case) {
        $values = ['extra' => ['id' => 4], 'second' => ['id' => 3], 'required' => ['id' => 1], 'first' => ['id' => 2]];
        if ($case < 4) {
            $values[array_keys($values)[$case]]['id'] *= -1;
        } elseif ($case === 4) {
            unset($values['first']);
        }
        $json = json_encode($values);
        GraphTraceChild::$calls = [];
        $expected = Json::decode($json, GraphTraceParent::class);
        $calls = GraphTraceChild::$calls;
        GraphTraceChild::$calls = [];
        $actual = DirectParser::decode($json, GraphTraceParent::class, $mode);
        ++$count;
        if (
            $calls !== GraphTraceChild::$calls
            || (
                $expected instanceof DecodeError
                    ? !$actual instanceof DecodeError || $actual->getMessage() !== $expected->getMessage()
                    : serialize($actual) !== serialize($expected)
            )
        ) {
            $failures[] = ['optional-construction-order', $mode, $case, $calls, GraphTraceChild::$calls];
        }
    }
}

foreach (Cases\SupportedDocumentRoundTripCases::objects() as $name => [$json, $type]) {
    foreach ([
        'graph-flex-bulk',
        'graph-flex-inline-direct-bulk',
        'graph-flex-inline-direct-bulk-guard',
        'graph-flex-inline-direct-bulk-header',
    ] as $mode) {
        $before = \Eventjet\Json\Benchmark\Prototype\GraphParser::$matches;
        DirectParser::decode($json, $type, $mode);
        ++$count;
        if (\Eventjet\Json\Benchmark\Prototype\GraphParser::$matches === $before) {
            $failures[] = ['whole-graph-path-not-exercised', $name, $mode];
        }
    }
}

// Mutate canonical inputs so the new typed grammars, rather than only fallback
// paths, face malformed numbers, whitespace, delimiters, escapes and UTF-8.
mt_srand(831);
$seed = '{"id":1,"name":"text","amount":1.5,"active":true,"note":null}';
$mutations = [' ', "\t", "\n", "\r", "\x0b", "\xff", '0', '-', 'e', '"', '\\', ':', ',', '[', ']', '{', '}'];
for ($index = 0; $index < 1000; ++$index) {
    $position = mt_rand(0, strlen($seed) - 1);
    $json = substr_replace($seed, $mutations[mt_rand(0, count($mutations) - 1)], $position, $index % 2);
    $compare('typed-grammar-mutation-' . $index, $json, Record::class);
}

// Warm declaration parsing, which also uses PCRE in the production decoder.
// Clear prototype compiler caches so resource failures exercise cold compilation.
$resourceFixtures = [];
foreach ([
    'scalar object',
    'record batch 1000',
    'deep chain 384',
    'Stripe invoice',
    'GitHub pull request webhook',
    'Kubernetes deployment',
    'ignored tree',
] as $scenario) {
    [$json, $type] = \Eventjet\Json\Benchmark\Prototype\workload($scenario);
    $resourceFixtures[$scenario] = [$json, $type, serialize(Json::decode($json, $type))];
}
$compilerCache = new ReflectionProperty(DirectParser::class, 'compiled');
$graphCache = new ReflectionProperty(\Eventjet\Json\Benchmark\Prototype\GraphParser::class, 'cache');
$savedCompiler = $compilerCache->getValue();
$savedGraph = $graphCache->getValue();
foreach ([
    ['pcre.backtrack_limit', '1'],
    ['pcre.recursion_limit', '1'],
] as [$setting, $limit]) {
    $old = ini_set($setting, $limit);
    $compilerCache->setValue(null, []);
    $graphCache->setValue(null, []);
    try {
        foreach ($resourceFixtures as $scenario => [$json, $type, $expected]) {
            foreach (['extreme', 'graph-flex-inline-direct-bulk', 'columns-8192'] as $mode) {
                $actual = DirectParser::decode($json, $type, $mode);
                ++$count;
                if (
                    $actual instanceof DecodeError
                    || serialize($actual) !== $expected
                    || ini_get($setting) !== $limit
                ) {
                    $failures[] = ['resource-limit-fallback', $setting, $scenario, $mode];
                }
            }
        }
    } finally {
        ini_set($setting, $old);
        $compilerCache->setValue(null, $savedCompiler);
        $graphCache->setValue(null, $savedGraph);
    }
}

echo json_encode(['checks' => $count, 'failures' => $failures], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
exit($failures === [] ? 0 : 1);
