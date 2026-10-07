<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor;
use RuntimeException;

use function str_replace;

/** @internal */
final class RootMapErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: JsonType<list<mixed>|object>, 2: string, 3: int, 4?: RuntimeException}>
     */
    public static function errors(): iterable
    {
        foreach ([
            '[]' => 'array',
            '[{}]' => 'array',
            'null' => 'null',
            'true' => 'bool',
            '1' => 'int',
            '1.5' => 'float',
            '"ready"' => 'string',
        ] as $json => $kind) {
            yield 'map root ' . $json => [
                (string) $json,
                JsonType::map(EmptyObject::class),
                'Expected the JSON root to be an object, got ' . $kind . '.',
                2,
            ];
        }
        foreach (RootArrayErrorCases::errors() as $name => [$json, $type, $message, $code]) {
            if ($code !== 3) {
                continue;
            }
            yield 'inside map ' . $name => [
                '{"entry":' . $json . '}',
                JsonType::map($type),
                str_replace(['Field [0]', 'Field [1]'], ['Field [entry][0]', 'Field [entry][1]'], $message),
                $code,
            ];
            if ($json === '[]') {
                yield 'empty map ' . $name => ['{}', JsonType::map($type), $message, $code];
            }
        }
        foreach (['0', '42', '-1'] as $key) {
            yield 'numeric map key ' . $key => [
                '{"' . $key . '":{}}',
                JsonType::map(EmptyObject::class),
                'Could not create '
                    . EmptyObject::class
                    . ' from the JSON object: Field  has numeric-looking member name '
                    . $key
                    . ', which PHP converts to an integer array key. Supported maps require member names that remain strings.',
                3,
            ];
        }
        foreach (['[]' => 'array', 'null' => 'null', '1' => 'int'] as $json => $kind) {
            yield 'nested map shape ' . $json => [
                '{"group":' . $json . '}',
                JsonType::map(JsonType::map(EmptyObject::class)),
                'Could not create '
                    . EmptyObject::class
                    . ' from the JSON object: Field [group] must be of type JSON object, '
                    . $kind
                    . ' given.',
                3,
            ];
        }
        yield from self::listShapes();
        yield 'quoted map key and list field path' => [
            '{"a.b":[{"firstName":1,"lastName":"Lovelace"}]}',
            JsonType::map(JsonType::array(Person::class)),
            'Could not create '
                . Person::class
                . ' from the JSON object: Field ["a.b"][0].firstName must be of type string, int given.',
            3,
        ];
        yield 'direct enum value error' => [
            '{"status":null}',
            JsonType::map(StringBackedStatus::class),
            'Could not create '
                . StringBackedStatus::class
                . ' from the JSON object: Field [status] uses backed enum '
                . StringBackedStatus::class
                . ', which expects a string backing value; null given.',
            3,
        ];
        yield 'direct object value error' => [
            '{"value":[]}',
            JsonType::map(EmptyObject::class),
            'Could not create '
                . EmptyObject::class
                . ' from the JSON object: Field [value] must be of type '
                . EmptyObject::class
                . ', array given.',
            3,
        ];
        yield 'invalid map JSON' => ['{"entry":', JsonType::map(EmptyObject::class), 'Invalid JSON: Syntax error', 1];
        yield from self::construction();
    }

    /** @return iterable<string, array{string, JsonType<list<mixed>>, string, int}> */
    private static function listShapes(): iterable
    {
        yield 'map inside list rejects array' => [
            '[[]]',
            JsonType::array(JsonType::map(EmptyObject::class)),
            'Could not create '
                . EmptyObject::class
                . ' from the JSON object: Field [0] must be of type JSON object, array given.',
            3,
        ];
        yield 'nested list of maps rejects object' => [
            '[{}]',
            JsonType::array(JsonType::array(JsonType::map(EmptyObject::class))),
            'Could not create '
                . EmptyObject::class
                . ' from the JSON object: Field [0] must be of type list<ArrayObject<string, '
                . EmptyObject::class
                . '>>, stdClass given.',
            3,
        ];
        yield 'nested map retains numeric key path' => [
            '[{"0":{}}]',
            JsonType::array(JsonType::map(EmptyObject::class)),
            'Could not create '
                . EmptyObject::class
                . ' from the JSON object: Field [0] has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            3,
        ];
    }

    /** @return iterable<string, array{string, JsonType<object>, string, int, RuntimeException}> */
    private static function construction(): iterable
    {
        $json = '{"value":"rejected"}';
        $target = JsonType::map(ThrowingConstructor::class);
        foreach ([1, 2, 3, 4, 5, 6] as $depth) {
            $json = '{"branch":' . $json . '}';
            yield 'map constructor exception depth ' . $depth => [
                $json,
                $target,
                'Could not create '
                    . ThrowingConstructor::class
                    . ' from the JSON object: The constructor rejected the decoded value.',
                3,
                ThrowingConstructor::exception(),
            ];
            $target = JsonType::map($target);
        }
    }
}
