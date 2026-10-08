<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use ArrayObject;
use Eventjet\Json\Benchmark\Fixtures\ScalarLists;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\DisjointStringBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionCollectionFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
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
            'public scalar properties' => self::publicScalars(),
            'named enums' => new BackedEnumFields(
                StringBackedStatus::Ready,
                StringBackedStatus::Pending,
                IntBackedStatus::Ready,
            ),
            'enum union' => new DistinctEnumScalarUnionField(IntBackedStatus::Ready),
            'enum union collections' => self::enumUnions(),
            'scalar lists' => self::scalarLists(2),
            'long scalar lists' => self::scalarLists(1000),
            'enum-heavy object collections' => self::collections(),
            'recursive collections' => self::recursive(),
            'enum-heavy root array' => array_fill(0, count: 100, value: self::collections()),
            default => throw new InvalidArgumentException('Unknown benchmark scenario: ' . $scenario),
        };
    }

    public static function scalarLists(int $size): ScalarLists
    {
        $strings = [];
        $integers = [];
        $floats = [];
        $booleans = [];
        for ($index = 0; $index < $size; ++$index) {
            $strings[] = 'value-' . $index;
            $integers[] = $index - 500;
            $floats[] = (float) ($index / 4) + 0.5;
            $booleans[] = ($index % 2) === 0;
        }
        return new ScalarLists($strings, $integers, $floats, $booleans);
    }

    private static function publicScalars(): ConstructorlessPublicProperties
    {
        $original = new ConstructorlessPublicProperties();
        $original->string = 'hello';
        $original->integer = 42;
        $original->float = 1.5;
        $original->boolean = true;
        $original->nullable = 'present';
        return $original;
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

    /**
     * @api Used by PHPBench parameter providers.
     * @return iterable<string, array{scenario: string}>
     */
    public static function smallScenarios(): iterable
    {
        foreach ([
            'scalar object',
            'public scalar properties',
            'scalar lists',
            'named enums',
            'enum union',
        ] as $scenario) {
            yield $scenario => ['scenario' => $scenario];
        }
    }

    /**
     * @api Used by PHPBench parameter providers.
     * @return iterable<string, array{scenario: string}>
     */
    public static function listScenario(): iterable
    {
        yield 'long scalar lists' => ['scenario' => 'long scalar lists'];
    }

    /**
     * @api Used by PHPBench parameter providers.
     * @return iterable<string, array{scenario: string}>
     */
    public static function diagnosticScenarios(): iterable
    {
        yield from self::smallScenarios();
        yield from self::listScenario();
    }

    /**
     * @api Used by PHPBench parameter providers.
     * @return iterable<string, array{scenario: string}>
     */
    public static function stressScenarios(): iterable
    {
        foreach ([
            'enum union collections',
            'enum-heavy object collections',
            'recursive collections',
            'enum-heavy root array',
        ] as $scenario) {
            yield $scenario => ['scenario' => $scenario];
        }
    }
}
