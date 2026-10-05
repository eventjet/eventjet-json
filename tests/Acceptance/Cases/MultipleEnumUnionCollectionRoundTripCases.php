<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function sprintf;

/** @internal */
final class MultipleEnumUnionCollectionRoundTripCases
{
    /** @return iterable<string, array{MultipleEnumUnionCollectionFields}> */
    public static function objects(): iterable
    {
        $mixedBackingValues = [...StringBackedStatus::cases(), ...IntBackedStatus::cases()];
        $stringBackingValues = [...StringBackedStatus::cases(), ...StringBackedOutcome::cases()];

        foreach ($mixedBackingValues as $mixedIndex => $mixedValue) {
            foreach ($stringBackingValues as $stringIndex => $stringValue) {
                $mixedProperty = new MultipleEnumUnionPublicProperty();
                $mixedProperty->value = $mixedValue;
                $stringProperty = new DisjointStringBackedEnumUnionPublicProperty();
                $stringProperty->value = $stringValue;

                foreach ([false, true] as $emptyList) {
                    foreach ([false, true] as $emptyMap) {
                        $object = new MultipleEnumUnionCollectionFields(
                            $emptyList ? [] : [new MultipleEnumUnionField($mixedValue)],
                            ['01' => new DisjointStringBackedEnumUnionField($stringValue)],
                        );
                        $object->publicList = [$mixedProperty];
                        $object->publicMap = new ArrayObject(
                            $emptyMap
                                ? []
                                : ['+1' => $stringProperty, '1.0' => $stringProperty, '1e0' => $stringProperty],
                        );

                        yield sprintf(
                            'multiple enum unions inside collections %d/%d/%d/%d',
                            $mixedIndex,
                            $stringIndex,
                            (int) $emptyList,
                            (int) $emptyMap,
                        ) => [$object];
                    }
                }
            }
        }
    }
}
