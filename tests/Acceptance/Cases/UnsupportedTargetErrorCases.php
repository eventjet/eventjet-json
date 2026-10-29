<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\InheritedPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class UnsupportedTargetErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::rootTypes();
        yield from self::nestedTypes();
        yield from self::serializedEnums();
        yield from FieldClassErrorCases::errors();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function rootTypes(): iterable
    {
        yield 'abstract root class cannot be instantiated' => [
            '{}',
            AbstractRootTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget from the JSON object: Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'interface root cannot be instantiated' => [
            '{}',
            RootTargetInterface::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'non-backed enum cannot be a root target' => [
            '{}',
            NonBackedStatus::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        foreach ([
            'int' => [IntBackedStatus::class, '{"name":"Ready","value":1}'],
            'string' => [StringBackedStatus::class, '{"name":"Ready","value":"ready"}'],
            'custom serialization' => [JsonSerializableStatus::class, '{"name":"Ready","value":"ready"}'],
        ] as $label => [$enum, $json]) {
            yield 'backed enum with ' . $label . ' values cannot be a root target' => [
                $json,
                $enum,
                'Could not create '
                    . $enum
                    . ' from the JSON object: Backed enums are not supported as standalone targets. Use a typed field or a collection descriptor.',
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        yield 'root class cannot implement JsonSerializable' => [
            '{}',
            JsonSerializableTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function nestedTypes(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create('\\BackedEnum', '', $tag);
            yield 'BackedEnum interface is not a concrete enum ' . $tag => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses interface BackedEnum, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        yield 'list item type cannot implement JsonSerializable' => [
            '[]',
            JsonType::array(JsonSerializableTarget::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $enumList = CollectionDeclarationFixture::create(
            'array',
            PhpType::list(JsonSerializableStatus::class),
            'param',
        );
        yield 'list item enum cannot implement JsonSerializable' => [
            '{"value":["ready"]}',
            $enumList,
            'Could not create '
                . $enumList
                . ' from the JSON object: Field value uses enum Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableStatus, which implements JsonSerializable. Its custom JSON representation may not match its cases, so it cannot satisfy the round-trip contract.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'root list enum cannot implement JsonSerializable' => [
            '[]',
            JsonType::array(JsonSerializableStatus::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableStatus from the JSON object: Target type is an enum that implements JsonSerializable. Its custom JSON representation may not match its cases, so it cannot satisfy the round-trip contract.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $objectItems = CollectionDeclarationFixture::create('array', 'list<\\stdClass>', 'var');
        yield 'stdClass list items are unsupported' => [
            '{}',
            $objectItems,
            'Could not create '
                . $objectItems
                . ' from the JSON object: Field value uses unsupported type stdClass. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'list item type cannot be an interface' => [
            '[]',
            JsonType::array(RootTargetInterface::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'list item type cannot be an abstract class' => [
            '[]',
            JsonType::array(AbstractRootTarget::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget from the JSON object: Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $nonFinal = 'Could not create Eventjet\Json\Test\Acceptance\Fixtures\InheritedPublicProperty from the JSON object: Target type is a non-final class. Collection items may be subclasses, whose runtime class JSON does not identify.';
        yield 'list item type cannot be a non-final class' => [
            '[]',
            JsonType::array(InheritedPublicProperty::class),
            $nonFinal,
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'map value type cannot be a non-final class' => [
            '{}',
            JsonType::map(InheritedPublicProperty::class),
            $nonFinal,
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'nested descriptor item type cannot be a non-final class' => [
            '[]',
            JsonType::array(JsonType::map(InheritedPublicProperty::class)),
            $nonFinal,
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'list item type cannot be a non-backed enum' => [
            '[]',
            JsonType::array(NonBackedStatus::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'list item type cannot have a private constructor' => [
            '[]',
            JsonType::array(PrivateConstructorTarget::create()::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget from the JSON object: Target class has a private constructor, which cannot be called to create the object.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'imported interface cannot be a collection item type' => [
            '{}',
            CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;'),
            'Could not create '
                . CollectionNameSource::load('CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested\Target_d2d5fe3230fd08878644563a847934cf61283e8a', 'namespace CollectionNames_d2d5fe3230fd08878644563a847934cf61283e8a\Nested; use \Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface as Item;
final class Target_d2d5fe3230fd08878644563a847934cf61283e8a {/** @param list<Item> $value */ public function __construct(public array $value) {}} final class LocalPerson {public int $value = 42;} namespace After_d2d5fe3230fd08878644563a847934cf61283e8a; use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;')
                . ' from the JSON object: Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'non-final nested class is rejected' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"value":{},"label":"value"}',
            new ParentClassField(new Person('Ada', 'Lovelace'), new ParentClassFieldBase(), 'value')::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField from the JSON object: Field value uses non-final class Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase. Values may be subclasses, whose runtime class JSON does not identify.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    private static function serializedEnums(): iterable
    {
        $enum = JsonSerializableStatus::class;
        foreach ([
            'field' => ['\\' . $enum, '', 'value'],
            'nullable field' => ['?\\' . $enum, '', 'value'],
            'native union member' => ['\\' . $enum . '|int', '', 'value'],
            'PHPDoc union member' => ['array', PhpType::list(PhpType::union($enum, PhpType::Int)), 'value'],
            'list item' => ['array', PhpType::list($enum), 'value'],
            'map value' => ['\\ArrayObject', PhpType::arrayObject($enum), 'value'],
            'tuple position' => ['array', PhpType::tuple($enum), 'value[0]'],
            'literal case' => ['\\' . $enum, '\\' . $enum . '::Ready', 'value'],
        ] as $position => [$native, $declaration, $path]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield 'backed enum with custom serialization is rejected as ' . $position . ' in ' . $tag => [
                    '{}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field '
                        . $path
                        . ' uses enum '
                        . $enum
                        . ', which implements JsonSerializable. Its custom JSON representation may not match its cases, so it cannot satisfy the round-trip contract.',
                    DecodeErrorKind::UnsupportedDeclaration,
                ];
            }
        }
    }
}
