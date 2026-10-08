<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\ProtectedConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use stdClass;

use function str_repeat;

/** @internal */
final class RootArrayErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, JsonType<list<mixed>>, string, int}>
     */
    public static function errors(): iterable
    {
        yield from CachedPublicPropertyErrorCases::errors();

        foreach ([
            'null' => 'null',
            'true' => 'bool',
            '1' => 'int',
            '1.5' => 'float',
            '"value"' => 'string',
            '{}' => 'stdClass',
            '{"0":{}}' => 'stdClass',
        ] as $json => $type) {
            yield 'root ' . $json => [
                (string) $json,
                JsonType::array(Person::class),
                'Expected the JSON root to be an array, got ' . $type . '.',
                2,
            ];
        }

        foreach ([
            'null' => 'null',
            'true' => 'bool',
            '1' => 'int',
            '"value"' => 'string',
            '[]' => 'array',
        ] as $json => $type) {
            foreach ([1, 2, 3] as $depth) {
                $path = str_repeat('[0]', $depth);
                $message =
                    'Could not create '
                    . EmptyObject::class
                    . ' from the JSON object: Field '
                    . $path
                    . ' must be of type '
                    . EmptyObject::class
                    . ', '
                    . $type
                    . ' given.';
                yield 'item ' . $json . ' depth ' . $depth => [
                    RootArrayInputs::wrap((string) $json, $depth),
                    RootArrayInputs::target(EmptyObject::class, $depth),
                    $message,
                    3,
                ];
            }
        }

        foreach (['{}' => 'stdClass', 'null' => 'null', '1' => 'int'] as $json => $type) {
            yield 'nested list ' . $json => [
                '[' . (string) $json . ']',
                JsonType::array(JsonType::array(Person::class)),
                'Could not create '
                    . Person::class
                    . ' from the JSON object: Field [0] must be of type list<'
                    . Person::class
                    . '>, '
                    . $type
                    . ' given.',
                3,
            ];
        }

        yield 'full field path' => [
            '[[],[{"firstName":1,"lastName":"Lovelace"}]]',
            JsonType::array(JsonType::array(Person::class)),
            'Could not create '
                . Person::class
                . ' from the JSON object: Field [1][0].firstName must be of type string, int given.',
            3,
        ];
        yield from self::enums();
        yield from self::unknownClass(Person::class);
        yield from self::declarations();
        yield from WarmCollectionErrorCases::errors();
    }

    /** @return iterable<string, array{string, JsonType<list<mixed>>, string, int}> */
    private static function enums(): iterable
    {
        yield 'string enum wrong backing' => [
            '[1]',
            JsonType::array(StringBackedStatus::class),
            'Could not create '
                . StringBackedStatus::class
                . ' from the JSON object: Field [0] uses backed enum '
                . StringBackedStatus::class
                . ', which expects a string backing value; int given.',
            3,
        ];
        yield 'string enum unknown case' => [
            '["missing"]',
            JsonType::array(StringBackedStatus::class),
            'Could not create '
                . StringBackedStatus::class
                . ' from the JSON object: Field [0] uses backed enum '
                . StringBackedStatus::class
                . ", which has no case with backing value 'missing'.",
            3,
        ];
        yield 'integer enum wrong backing' => [
            '[["1"]]',
            JsonType::array(JsonType::array(IntBackedStatus::class)),
            'Could not create '
                . IntBackedStatus::class
                . ' from the JSON object: Field [0][0] uses backed enum '
                . IntBackedStatus::class
                . ', which expects a int backing value; string given.',
            3,
        ];
        yield 'integer enum unknown case' => [
            '[42]',
            JsonType::array(IntBackedStatus::class),
            'Could not create '
                . IntBackedStatus::class
                . ' from the JSON object: Field [0] uses backed enum '
                . IntBackedStatus::class
                . ', which has no case with backing value 42.',
            3,
        ];
    }

    /**
     * @param class-string $class
     * @return iterable<string, array{string, JsonType<list<mixed>>, string, int}>
     */
    private static function unknownClass(string $class): iterable
    {
        /** @var class-string $missingClass */
        $missingClass = $class . 'Missing';
        yield 'unknown item class' => [
            '[]',
            JsonType::array($missingClass),
            'Could not create ' . $missingClass . ' from the JSON object: Class "' . $missingClass . '" does not exist',
            3,
        ];
    }

    /** @return iterable<string, array{string, JsonType<list<mixed>>, string, int}> */
    private static function declarations(): iterable
    {
        $reasons = [
            RootTargetInterface::class =>
                'Field [] uses interface '
                    . RootTargetInterface::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            AbstractRootTarget::class =>
                'Field [] uses abstract class '
                    . AbstractRootTarget::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            stdClass::class => 'Field [] uses non-final class stdClass. Values may be subclasses, whose runtime class JSON does not identify.',
            PrivateConstructorTarget::class => 'Target class has a private constructor, which cannot be called to create the object.',
            ProtectedConstructorTarget::class => 'Target class has a protected constructor, which cannot be called to create the object.',
            JsonSerializableTarget::class => 'Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            NonBackedStatus::class =>
                'Target type non-backed enum '
                    . NonBackedStatus::class
                    . ', which cannot be decoded because it has no backing value.',
        ];
        foreach ($reasons as $class => $reason) {
            foreach ([1, 2, 3] as $depth) {
                yield 'unsupported declaration ' . $class . ' depth ' . $depth => [
                    RootArrayInputs::wrap('[]', $depth - 1),
                    RootArrayInputs::target($class, $depth),
                    'Could not create ' . $class . ' from the JSON object: ' . $reason,
                    3,
                ];
            }
        }
    }
}
