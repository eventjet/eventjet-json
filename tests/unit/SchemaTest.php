<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\ArrayOf;
use Eventjet\Json\Schema;
use Eventjet\Test\Unit\Json\Fixtures\AllSimpleTypes;
use Eventjet\Test\Unit\Json\Fixtures\ArrayOfStrings;
use Eventjet\Test\Unit\Json\Fixtures\Arrays;
use Eventjet\Test\Unit\Json\Fixtures\MissingArrayOfAttribute;
use Eventjet\Test\Unit\Json\Fixtures\MissingConstructorArgumentType;
use Eventjet\Test\Unit\Json\Fixtures\MultipleArrayOfAttributes;
use Eventjet\Test\Unit\Json\Fixtures\OptionalStringDefaultNull;
use Eventjet\Test\Unit\Json\Fixtures\RequiredMixed;
use Eventjet\Test\Unit\Json\Fixtures\RequiredNestedObject;
use Eventjet\Test\Unit\Json\Fixtures\RequiredString;
use Eventjet\Test\Unit\Json\Fixtures\TakesNonBackedEnum;
use Eventjet\Test\Unit\Json\Fixtures\TakesStringEnum;
use Eventjet\Test\Unit\Json\Fixtures\UnionWithoutClasses;
use Eventjet\Test\Unit\Json\Fixtures\UnknownArrayItemClass;
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
     * @return iterable<string, array{class-string|ArrayOf, array<string, mixed>}>
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
        yield ArrayOfStrings::class => [
            ArrayOfStrings::class,
            [
                'type' => 'object',
                'properties' => [
                    'strings' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['strings'],
                'additionalProperties' => false,
            ],
        ];
        yield Arrays::class => [
            Arrays::class,
            [
                'type' => 'object',
                'properties' => [
                    'strings' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'ints' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                    'floats' => [
                        'type' => 'array',
                        'items' => ['type' => 'number'],
                    ],
                    'bools' => [
                        'type' => 'array',
                        'items' => ['type' => 'boolean'],
                    ],
                    'nulls' => [
                        'type' => 'array',
                        'items' => ['type' => 'null'],
                    ],
                    'objects' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string']],
                            'required' => ['name'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'stringEnums' => [
                        'type' => 'array',
                        'items' => ['enum' => ['yay', 'nay']],
                    ],
                    'intEnums' => [
                        'type' => 'array',
                        'items' => ['enum' => [42, 69]],
                    ],
                    'stringArrays' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
                'required' => [
                    'strings',
                    'ints',
                    'floats',
                    'bools',
                    'nulls',
                    'objects',
                    'stringEnums',
                    'intEnums',
                    'stringArrays',
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return iterable<string, array{string|ArrayOf, string}>
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
        /**
         * @psalm-suppress MixedArgument
         * @psalm-suppress UndefinedClass
         */
        yield 'Unknown array item type' => [
            /** @phpstan-ignore-next-line class.notFound */
            new ArrayOf(NonExistentType::class),
            sprintf(
                'Failed to infer schema for array items: Cannot infer schema: class %s does not exist.',
                /** @phpstan-ignore-next-line class.notFound */
                NonExistentType::class,
            ),
        ];
        yield UnknownArrayItemClass::class => [
            UnknownArrayItemClass::class,
            sprintf(
                'Failed to infer schema for array parameter %s::items: Cannot infer schema: class Eventjet\Test\Unit\Json\Fixtures\DoesNotExist does not exist.',
                UnknownArrayItemClass::class,
            ),
        ];
        yield MissingArrayOfAttribute::class => [
            MissingArrayOfAttribute::class,
            sprintf(
                'Failed to infer schema for array parameter %s::items: Missing #[ArrayOf] attribute to specify the item type.',
                MissingArrayOfAttribute::class,
            ),
        ];
        yield MultipleArrayOfAttributes::class => [
            MultipleArrayOfAttributes::class,
            sprintf(
                'Failed to infer schema for array parameter %s::bools: Multiple #[ArrayOf] attributes found; only one is allowed.',
                MultipleArrayOfAttributes::class,
            ),
        ];
    }

    /**
     * @param class-string|ArrayOf $type
     * @param array<string, mixed>|bool $expectedSchema
     */
    #[DataProvider('provideInferFromTypeCases')]
    public function testInferFromType(string|ArrayOf $type, array|bool $expectedSchema): void
    {
        $schema = Schema::inferFromType($type);

        if (!$schema instanceof Schema) {
            self::fail(sprintf('Failed to infer schema for type %s: %s', $type, $schema->getMessage()));
        }
        $actual = json_encode($schema, JSON_THROW_ON_ERROR);
        self::assertJsonStringEqualsJsonString(json_encode($expectedSchema, JSON_THROW_ON_ERROR), $actual);
    }

    #[DataProvider('provideFailToInferFromTypeCases')]
    public function testFailToInferFromType(string|ArrayOf $type, string $expectedMessage): void
    {
        $schema = Schema::inferFromType($type);

        self::assertInstanceOf(Throwable::class, $schema);
        self::assertSame($expectedMessage, $schema->getMessage());
    }
}
