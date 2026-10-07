<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use InvalidArgumentException;

use function array_fill;

/** @internal */
final class DecodeWorkloads
{
    /**
     * @return list<CombinedCollectionFields>|object
     * @throws InvalidArgumentException
     */
    public static function create(string $scenario): array|object
    {
        return match ($scenario) {
            'scalar object' => new ScalarFields('hello', 42, 1.5, true),
            'named enums' => new BackedEnumFields(
                StringBackedStatus::Ready,
                StringBackedStatus::Pending,
                IntBackedStatus::Ready,
            ),
            'enum union' => new DistinctEnumScalarUnionField(IntBackedStatus::Ready),
            'enum union collections' => self::enumUnions(),
            'scalar lists' => new ScalarListFields(['a', 'b'], [1, 2], [1.5, 2.5], [true, false]),
            'object collections' => self::collections(),
            'recursive collections' => self::recursive(),
            'root array' => array_fill(0, count: 100, value: self::collections()),
            default => throw new InvalidArgumentException('Unknown benchmark scenario: ' . $scenario),
        };
    }

    private static function enumUnions(): MultipleEnumUnionCollectionFields
    {
        return new MultipleEnumUnionCollectionFields(
            array_fill(0, count: 20, value: new MultipleEnumUnionField(IntBackedStatus::Ready)),
            ['first' => new DisjointStringBackedEnumUnionField(StringBackedStatus::Ready)],
        );
    }

    private static function collections(): CombinedCollectionFields
    {
        $enums = new BackedEnumFields(StringBackedStatus::Ready, null, IntBackedStatus::Ready);
        $original = new CombinedCollectionFields($enums, array_fill(0, count: 20, value: $enums), ['first' => $enums]);
        $original->publicMap = new ArrayObject(['second' => $enums]);
        return $original;
    }

    private static function recursive(): NestedSelfCollections
    {
        $original = new NestedSelfCollections();
        for ($depth = 0; $depth < 4; ++$depth) {
            $parent = new NestedSelfCollections([[$original]]);
            /** @var ArrayObject<string, NestedSelfCollections|null> $branch */
            $branch = new ArrayObject(['leaf' => $original]);
            $parent->maps = new ArrayObject(['branch' => $branch]);
            $original = $parent;
        }
        return $original;
    }
}
