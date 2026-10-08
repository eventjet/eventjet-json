<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

/** @internal */
final class ScalarFieldRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from self::group1();
        yield from self::group2();
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group1(): iterable
    {
        yield 'scalar fields with positive values' => [new ScalarFields('value', 42, 3.25, true)];
        yield 'scalar fields with a whole-valued float' => [new ScalarFields('value', 42, 3.0, true)];
        yield 'scalar fields with zero and false' => [new ScalarFields('', 0, 0.5, false)];
        yield 'nullable scalar fields with values' => [new NullableScalarFields('value', -42, -3.25, false, null)];
        yield 'nullable scalar fields set to null' => [new NullableScalarFields(null, null, null, null, null)];
        yield 'literal boolean fields' => [new LiteralBooleanFields(true, false)];
    }

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function group2(): iterable
    {
        yield 'constructorless public scalar properties' => [(static function (): object {
            $object = new ConstructorlessPublicProperties();
            $object->string = 'value';
            $object->integer = 42;
            $object->float = 3.0;
            $object->boolean = true;
            $object->nullable = null;
            return $object;
        })()];
    }
}
