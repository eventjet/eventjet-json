<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

/** @internal */
final class ScalarValidationErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::scalarTypes();
        yield from self::integerBoundaries();
        yield from self::literalBooleans();
        yield from self::cachedProperties();
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function scalarTypes(): iterable
    {
        yield 'string field rejects an integer' => [
            '{"string":42,"integer":42,"float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field string must be of type string, int given.',
            3,
        ];
        yield 'integer field rejects a numeric string' => [
            '{"string":"value","integer":"42","float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field integer must be of type int, string given.',
            3,
        ];
        yield 'float field rejects a numeric string' => [
            '{"string":"value","integer":42,"float":"3.25","boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field float must be of type float, string given.',
            3,
        ];
        yield 'boolean field rejects an integer' => [
            '{"string":"value","integer":42,"float":3.25,"boolean":1}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field boolean must be of type bool, int given.',
            3,
        ];
        yield 'nullable string property rejects an integer' => [
            '{"string":"value","integer":42,"float":3.25,"boolean":true,"nullable":42}',
            ConstructorlessPublicProperties::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties from the JSON object: Field nullable must be of type string|null, int given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function integerBoundaries(): iterable
    {
        yield 'integer above platform maximum' => [
            '{"string":"value","integer":9223372036854775808,"float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field integer must be of type int, float given.',
            3,
        ];
        yield 'integer below platform minimum' => [
            '{"string":"value","integer":-9223372036854775809,"float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field integer must be of type int, float given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function literalBooleans(): iterable
    {
        yield 'false literal rejects a string' => [
            '{"true":true,"false":""}',
            LiteralBooleanFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields from the JSON object: Field false must be of type false, string given.',
            3,
        ];
        yield 'true literal rejects a string' => [
            '{"true":"1","false":false}',
            LiteralBooleanFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields from the JSON object: Field true must be of type true, string given.',
            3,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function cachedProperties(): iterable
    {
        yield 'cached public properties still reject invalid values' => [
            '[{}, {"integer":"wrong"}]',
            JsonType::array(ConstructorlessPublicProperties::class),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties from the JSON object: Field [1].integer must be of type int, string given.',
            3,
        ];
    }
}
