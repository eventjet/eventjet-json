<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;

use function get_debug_type;

/** @internal */
final class PublicPropertyUnionRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{ArrayScalarUnionPublicProperty|ClassEnumScalarUnionPublicProperty|DisjointStringBackedEnumUnionPublicProperty|MultipleEnumUnionPublicProperty|UnionPublicProperty}>
     */
    public static function objects(): iterable
    {
        foreach (['value', 42] as $value) {
            $unionPublicProperty = new UnionPublicProperty();
            $unionPublicProperty->value = $value;

            yield 'scalar union public property with ' . get_debug_type($value) => [$unionPublicProperty];
        }

        foreach ([
            'string' => 'value',
            'string-keyed map' => ['answer' => 42],
            'list' => [1, 2],
        ] as $name => $value) {
            $unionPublicProperty = new ArrayScalarUnionPublicProperty();
            $unionPublicProperty->value = $value;

            yield 'array and scalar union public property with ' . $name => [$unionPublicProperty];
        }

        foreach ([new Person('Ada', 'Lovelace'), StringBackedStatus::Ready, 42, null] as $value) {
            $unionPublicProperty = new ClassEnumScalarUnionPublicProperty();
            $unionPublicProperty->value = $value;

            yield 'class, enum, scalar, and null union public property with ' . get_debug_type($value) => [
                $unionPublicProperty,
            ];
        }

        foreach ([
            StringBackedStatus::Ready,
            StringBackedStatus::Pending,
            IntBackedStatus::Ready,
            IntBackedStatus::Pending,
        ] as $value) {
            $unionPublicProperty = new MultipleEnumUnionPublicProperty();
            $unionPublicProperty->value = $value;

            yield 'different-backed-type enum union public property with ' . $value::class . '::' . $value->name => [
                $unionPublicProperty,
            ];
        }

        foreach ([
            StringBackedStatus::Ready,
            StringBackedStatus::Pending,
            StringBackedOutcome::Complete,
            StringBackedOutcome::Failed,
        ] as $value) {
            $unionPublicProperty = new DisjointStringBackedEnumUnionPublicProperty();
            $unionPublicProperty->value = $value;

            yield 'same-backed-type enum union public property with ' . $value::class . '::' . $value->name => [
                $unionPublicProperty,
            ];
        }
    }
}
