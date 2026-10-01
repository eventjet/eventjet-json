<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use LogicException;

use function explode;
use function get_debug_type;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class ScalarTypeMismatchCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws LogicException
     */
    public static function mismatches(): iterable
    {
        $mismatchedValues = self::mismatchedValues();

        foreach (self::fixtures() as $class => ['values' => $validValues, 'types' => $types]) {
            foreach ($types as $field => $expectedType) {
                $baseType = explode('|', $expectedType)[0];
                $mismatches = $mismatchedValues[$baseType] ?? throw new LogicException(
                    'Missing mismatch values for ' . $baseType . '.',
                );

                foreach ($mismatches as $case => $mismatchedValue) {
                    if ($mismatchedValue === null && $expectedType !== $baseType) {
                        continue;
                    }

                    $values = $validValues;
                    $values[$field] = $mismatchedValue;

                    yield $class . '::$' . $field . ' rejects ' . $case => [
                        json_encode($values, JSON_THROW_ON_ERROR),
                        $class,
                        'Could not create '
                            . $class
                            . ' from the JSON object: Field '
                            . $field
                            . ' must be of type '
                            . $expectedType
                            . ', '
                            . get_debug_type($mismatchedValue)
                            . ' given.',
                        3,
                    ];
                }
            }
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string}>
     */
    public static function numericKeyMismatches(): iterable
    {
        yield 'numeric object keys with coercible scalar values' => [
            '{"0":42,"1":"42","2":"3.25","3":1}',
            ScalarFields::class,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function outOfRangeIntegers(): iterable
    {
        foreach ([
            'above platform maximum' => '9223372036854775808',
            'below platform minimum' => '-9223372036854775809',
        ] as $name => $integer) {
            yield 'integer ' . $name => [
                '{"string":"value","integer":' . $integer . ',"float":3.25,"boolean":true}',
                ScalarFields::class,
                'Could not create '
                    . ScalarFields::class
                    . ' from the JSON object: Field integer must be of type int, float given.',
                3,
            ];
        }
    }

    /** @return array<class-string, array{values: array<string, scalar|null>, types: array<string, string>}> */
    private static function fixtures(): array
    {
        return [
            ScalarFields::class => [
                'values' => ['string' => 'value', 'integer' => 42, 'float' => 3.25, 'boolean' => true],
                'types' => ['string' => 'string', 'integer' => 'int', 'float' => 'float', 'boolean' => 'bool'],
            ],
            NullableScalarFields::class => [
                'values' => [
                    'string' => 'value',
                    'integer' => 42,
                    'float' => 3.25,
                    'boolean' => true,
                    'null' => null,
                ],
                'types' => [
                    'string' => 'string|null',
                    'integer' => 'int|null',
                    'float' => 'float|null',
                    'boolean' => 'bool|null',
                    'null' => 'null',
                ],
            ],
            LiteralBooleanFields::class => [
                'values' => ['true' => true, 'false' => false],
                'types' => ['true' => 'true', 'false' => 'false'],
            ],
        ];
    }

    /** @return array<string, array<string, scalar|array<never, never>|null>> */
    private static function mismatchedValues(): array
    {
        return [
            'string' => ['int' => 42, 'float' => 3.25, 'bool' => true, 'null' => null, 'array' => []],
            'int' => ['string' => '42', 'float' => 42.5, 'bool' => true, 'null' => null, 'array' => []],
            'float' => ['string' => '3.25', 'bool' => true, 'null' => null, 'array' => []],
            'bool' => ['string' => 'true', 'int' => 1, 'float' => 1.5, 'null' => null, 'array' => []],
            'null' => ['string' => '', 'int' => 0, 'float' => 0.5, 'bool' => false, 'array' => []],
            'true' => ['string' => '1', 'int' => 1, 'float' => 1.5, 'bool' => false, 'null' => null, 'array' => []],
            'false' => ['string' => '', 'int' => 0, 'float' => 0.5, 'bool' => true, 'null' => null, 'array' => []],
        ];
    }
}
