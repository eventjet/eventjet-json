<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionCollectionFields;

use function sprintf;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class UnionCollectionRoundTripCases
{
    /** @return iterable<string, array{UnionCollectionFields}> */
    public static function objects(): iterable
    {
        $values = [
            new Person('Grüße, 世界', '42', age: 0),
            ...StringBackedStatus::cases(),
            PHP_INT_MIN,
            0,
            PHP_INT_MAX,
            null,
        ];

        foreach ($values as $firstIndex => $first) {
            foreach ($values as $secondIndex => $second) {
                $firstProperty = new ClassEnumScalarUnionPublicProperty();
                $firstProperty->value = $first;
                $secondProperty = new ClassEnumScalarUnionPublicProperty();
                $secondProperty->value = $second;

                foreach ([false, true] as $empty) {
                    $object = new UnionCollectionFields(
                        $empty ? [] : [new ClassEnumScalarUnionField($first), new ClassEnumScalarUnionField($second)],
                        ['01' => $firstProperty, '+1' => $secondProperty],
                    );
                    $object->publicMap = new ArrayObject(
                        $empty ? [] : ['+1' => $secondProperty, '01' => $firstProperty],
                    );

                    yield sprintf('unions inside collections %d/%d/%d', $firstIndex, $secondIndex, (int) $empty) => [
                        $object,
                    ];
                }
            }
        }
    }
}
