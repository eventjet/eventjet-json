<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

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
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields::$string rejects int' => [
            '{"string":42,"integer":42,"float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field string must be of type string, int given.',
            3,
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields::$integer rejects string' => [
            '{"string":"value","integer":"42","float":3.25,"boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field integer must be of type int, string given.',
            3,
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields::$float rejects string' => [
            '{"string":"value","integer":42,"float":"3.25","boolean":true}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field float must be of type float, string given.',
            3,
        ];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields::$boolean rejects int' => [
            '{"string":"value","integer":42,"float":3.25,"boolean":1}',
            ScalarFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields from the JSON object: Field boolean must be of type bool, int given.',
            3,
        ];
    }
}
