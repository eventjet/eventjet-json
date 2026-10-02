<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\MapHolder;
use Eventjet\Json\Test\Acceptance\Fixtures\PrefixedMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\UntypedArrayField;

/** @internal */
final class MapRoundTripCases
{
    /** @return iterable<string, array{MapHolder|PrefixedMapField|UntypedArrayField}> */
    public static function objects(): iterable
    {
        yield 'array-valued field encoded as an object' => [new MapHolder(['answer' => 42])];
        yield 'array-valued field preserves nested objects and lists' => [
            new MapHolder(['items' => [['answer' => 42]]]),
        ];
        yield 'array field without a PHPDoc parameter declaration is passed through' => [new UntypedArrayField([
            'value',
        ])];
        yield 'untyped array field recursively normalizes JSON objects' => [new UntypedArrayField([
            'nested' => ['answer' => 42],
        ])];
        yield 'map declaration matches the complete parameter name' => [
            new PrefixedMapField([], ['primary' => 'value']),
        ];
    }
}
