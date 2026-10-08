<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\VariadicConstructorTarget;

/** @internal */
final class TargetsErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::targets1();
        yield from self::targets2();
        yield from self::group3();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function targets1(): iterable
    {
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties::$nullable rejects int' => [
            '{"string":"value","integer":42,"float":3.25,"boolean":true,"nullable":42}',
            ConstructorlessPublicProperties::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties from the JSON object: Field nullable must be of type string|null, int given.',
            3,
        ];
        yield 'empty document' => ['', Person::class, 'Invalid JSON: Syntax error', 1];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields::$false rejects string' => [
            '{"true":true,"false":""}',
            LiteralBooleanFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields from the JSON object: Field false must be of type false, string given.',
            3,
        ];
        yield 'root null' => [
            'null',
            JsonType::array(Person::class),
            'Expected the JSON root to be an array, got null.',
            2,
        ];
        yield 'private constructor' => [
            '{}',
            PrivateConstructorTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget from the JSON object: Target class has a private constructor, which cannot be called to create the object.',
            3,
        ];
        yield 'unsupported declaration Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget depth 1' => [
            '[]',
            JsonType::array(JsonSerializableTarget::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function targets2(): iterable
    {
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields::$true rejects string' => [
            '{"true":"1","false":false}',
            LiteralBooleanFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields from the JSON object: Field true must be of type true, string given.',
            3,
        ];
        yield 'abstract class root target' => [
            '{}',
            AbstractRootTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget from the JSON object: Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            3,
        ];
        yield 'interface root target' => [
            '{}',
            RootTargetInterface::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            3,
        ];
        yield 'variadic constructor rejects {}' => [
            '{}',
            VariadicConstructorTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\VariadicConstructorTarget from the JSON object: Constructor parameter values is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
            3,
        ];
        yield 'unsupported declaration Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface depth 1' => [
            '[]',
            JsonType::array(RootTargetInterface::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Field [] uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'fully qualified interface import/param' => [
            '{}',
            CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;'),
            'Could not create '
                . CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;')
                . ' from the JSON object: Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            3,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group3(): iterable
    {
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields publicStrings rejects integer member 0' => [
            '{"strings":{"valid":"value"},"integers":{"valid":42},"floats":{"valid":3.25},"booleans":{"valid":true},"publicStrings":{"0":"ready"}}',
            ScalarMapFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields from the JSON object: Field publicStrings has numeric-looking member name 0, which PHP converts to an integer array key. Supported maps require member names that remain strings.',
            3,
        ];
        yield 'JSON scalar: 0' => ['0', Person::class, 'Expected the JSON root to be an object, got int.', 2];
    }
}
