<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Schema;
use Eventjet\Test\Unit\Json\Fixtures\AllSimpleTypes;
use Eventjet\Test\Unit\Json\Fixtures\MissingConstructorArgumentType;
use Eventjet\Test\Unit\Json\Fixtures\OptionalStringDefaultNull;
use Eventjet\Test\Unit\Json\Fixtures\RequiredMixed;
use Eventjet\Test\Unit\Json\Fixtures\RequiredNestedObject;
use Eventjet\Test\Unit\Json\Fixtures\RequiredString;
use Eventjet\Test\Unit\Json\Fixtures\TakesNonBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\TakesStringEnum;
use Eventjet\Test\Unit\Json\Fixtures\UnionWithoutClasses;
use Eventjet\Test\Unit\Json\Fixtures\UnknownPropertyType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class SchemaTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, array<string, mixed>}>
     */
    public static function provideInferFromTypeCases(): iterable
    {
        yield RequiredString::class => [
            RequiredString::class,
            [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                'required' => ['name'],
                'additionalProperties' => false,
            ],
        ];
        yield OptionalStringDefaultNull::class => [
            OptionalStringDefaultNull::class,
            [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                'required' => [],
                'additionalProperties' => false,
            ],
        ];
        yield AllSimpleTypes::class => [
            AllSimpleTypes::class,
            [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                    'height' => ['type' => 'number'],
                    'isActive' => ['type' => 'boolean'],
                    'yes' => ['const' => true],
                    'no' => ['const' => false],
                    'nothing' => ['type' => 'null'],
                ],
                'required' => ['name', 'age', 'height', 'isActive', 'yes', 'no', 'nothing'],
                'additionalProperties' => false,
            ],
        ];
        yield TakesStringEnum::class => [
            TakesStringEnum::class,
            [
                'type' => 'object',
                'properties' => ['val' => ['enum' => ['yay', 'nay']]],
                'required' => ['val'],
                'additionalProperties' => false,
            ],
        ];
        yield RequiredMixed::class => [
            RequiredMixed::class,
            [
                'type' => 'object',
                'properties' => ['val' => true],
                'required' => ['val'],
                'additionalProperties' => false,
            ],
        ];
        yield RequiredNestedObject::class => [
            RequiredNestedObject::class,
            [
                'type' => 'object',
                'properties' => [
                    'obj' => [
                        'type' => 'object',
                        'properties' => ['name' => ['type' => 'string']],
                        'required' => [],
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['obj'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFailToInferFromTypeCases(): iterable
    {
        yield 'Unknown class' => [
            'Eventjet\Test\Unit\Json\Fixtures\NonExistentClass',
            'Cannot infer schema: class Eventjet\Test\Unit\Json\Fixtures\NonExistentClass does not exist.',
        ];
        yield MissingConstructorArgumentType::class => [
            MissingConstructorArgumentType::class,
            sprintf(
                'Property %s::val has no type. If you want it to accept anything, use "mixed" as type.',
                MissingConstructorArgumentType::class,
            ),
        ];
        yield UnionWithoutClasses::class => [
            UnionWithoutClasses::class,
            sprintf(
                'Property %s::val has an unsupported union or intersection type.',
                UnionWithoutClasses::class,
            ),
        ];
        yield UnknownPropertyType::class => [
            UnknownPropertyType::class,
            sprintf(
                'Failed to infer schema for property %s::unknown: Cannot infer schema: class Eventjet\Test\Unit\Json\Fixtures\Unknown does not exist.',
                UnknownPropertyType::class,
            ),
        ];
        yield TakesNonBackedEnum::class => [
            TakesNonBackedEnum::class,
            sprintf(
                'Failed to infer schema for property %s::value: Only backed enums are supported, Eventjet\Test\Unit\Json\Fixtures\NonBackedEnum is not.',
                TakesNonBackedEnum::class,
            ),
        ];
    }

    /**
     * @param class-string $type
     * @param array<string, mixed>|bool $expectedSchema
     */
    #[DataProvider('provideInferFromTypeCases')]
    public function testInferFromType(string $type, array|bool $expectedSchema): void
    {
        $schema = Schema::inferFromType($type);

        self::assertInstanceOf(Schema::class, $schema);
        $actual = json_encode($schema, JSON_THROW_ON_ERROR);
        self::assertJsonStringEqualsJsonString(json_encode($expectedSchema, JSON_THROW_ON_ERROR), $actual);
    }

    #[DataProvider('provideFailToInferFromTypeCases')]
    public function testFailToInferFromType(string $type, string $expectedMessage): void
    {
        $schema = Schema::inferFromType($type);

        self::assertInstanceOf(Throwable::class, $schema);
        self::assertSame($expectedMessage, $schema->getMessage());
    }
}
