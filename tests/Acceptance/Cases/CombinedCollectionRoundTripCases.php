<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function array_slice;
use function sprintf;

/** @internal */
final class CombinedCollectionRoundTripCases
{
    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        $values = [];

        foreach (StringBackedStatus::cases() as $stringStatus) {
            foreach ([null, ...StringBackedStatus::cases()] as $nullableStatus) {
                foreach (IntBackedStatus::cases() as $intStatus) {
                    $values[] = new BackedEnumFields($stringStatus, $nullableStatus, $intStatus);
                }
            }
        }

        foreach ($values as $index => $nested) {
            foreach ([0, 1, 3] as $length) {
                foreach (['primary' => false, '01' => false, '+1' => false, 'empty' => true] as $key => $emptyMap) {
                    $object = new CombinedCollectionFields($nested, array_slice($values, offset: 0, length: $length), [
                        $key => $nested,
                    ]);
                    $object->publicMap = new ArrayObject($emptyMap ? [] : [$key => $nested]);

                    yield sprintf('combined collections %d/%d/%s/%d', $index, $length, $key, (int) $emptyMap) => [
                        $object,
                    ];
                }
            }
        }
    }
}
