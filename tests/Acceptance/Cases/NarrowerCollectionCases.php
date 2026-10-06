<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class NarrowerCollectionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (self::declarations() as [$native, $declaration, $shape, $values]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                $reflection = new ReflectionClass($class);
                foreach (CollectionUnionInputs::collections($shape, $values) as $index => $collection) {
                    $object = $tag === 'param' ? $reflection->newInstance($collection) : $reflection->newInstance();
                    if ($tag === 'var') {
                        $reflection->getProperty('value')->setValue($object, $collection);
                    }
                    yield 'narrower collection ' . $declaration . $tag . $index => [$object];
                }
            }
        }
    }

    /** @return iterable<array{string, string, string, list<bool|float|int|string|object|null>}> */
    private static function declarations(): iterable
    {
        foreach (CollectionUnionCases::shapes('non-zero-int') as $shape => [$native, $declaration]) {
            yield [$native, $declaration, $shape, [PHP_INT_MIN, -1, 0, 1, PHP_INT_MAX]];
        }
        foreach ([
            'string' => ['', '42', '雪'],
            'int' => [PHP_INT_MIN, 0, PHP_INT_MAX],
            'float' => [0.0, 3.0, -1.25],
            'bool' => [true, false],
            '\\' . StringBackedStatus::class => [StringBackedStatus::Ready, StringBackedStatus::Pending],
            '\\' . Coordinates::class => [new Coordinates(1.25, 2.5)],
        ] as $type => $values) {
            yield ['array', 'non-empty-map<string, ' . $type . '>', 'map', $values];
        }
    }
}
