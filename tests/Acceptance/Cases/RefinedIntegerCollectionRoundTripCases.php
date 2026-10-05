<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\RefinedIntegerCollectionFields;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class RefinedIntegerCollectionRoundTripCases
{
    /** @return iterable<string, array{RefinedIntegerCollectionFields}> */
    public static function objects(): iterable
    {
        yield 'refined integer collections preserve empty lists and maps' => [new RefinedIntegerCollectionFields()];

        /** @var list<negative-int> $negativeValues */
        $negativeValues = [-1, -42, PHP_INT_MIN];

        foreach ([1, 42, PHP_INT_MAX] as $positive) {
            foreach ($negativeValues as $negative) {
                $fields = new RefinedIntegerCollectionFields([$positive, 1], ['negative' => $negative, 'other' => -1]);
                $fields->nonNegative = [0, $positive];
                $fields->nonPositive['zero'] = 0;
                $fields->nonPositive['negative'] = $negative;

                yield 'refined integer collections: ' . $positive . ', ' . $negative => [$fields];
            }
        }
    }
}
