<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;

/** @internal */
final class UnsupportedTargetErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::rootTypes();
        yield from self::nestedTypes();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function rootTypes(): iterable
    {
        yield 'abstract root class cannot be instantiated' => [
            '{}',
            AbstractRootTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget from the JSON object: Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            3,
        ];
        yield 'interface root cannot be instantiated' => [
            '{}',
            RootTargetInterface::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            3,
        ];
        yield 'non-backed enum cannot be a root target' => [
            '{}',
            NonBackedStatus::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedTypes(): iterable
    {
        yield 'list item type cannot implement JsonSerializable' => [
            '[]',
            JsonType::array(JsonSerializableTarget::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            3,
        ];
        yield 'list item type cannot be an interface' => [
            '[]',
            JsonType::array(RootTargetInterface::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Field [] uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'imported interface cannot be a collection item type' => [
            '{}',
            CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;'),
            'Could not create '
                . CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;')
                . ' from the JSON object: Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            3,
        ];
        yield 'non-final nested class is rejected' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"value":{},"label":"value"}',
            ParentClassField::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField from the JSON object: Field value uses non-final class Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase. Values may be subclasses, whose runtime class JSON does not identify.',
            3,
        ];
    }
}
