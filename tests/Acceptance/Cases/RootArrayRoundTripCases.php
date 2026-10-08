<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function array_chunk;
use function bin2hex;

/** @internal */
final class RootArrayRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<object>, string|null, JsonType<list<object>>}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        /** @var array<class-string, list<object>> $groups */
        $groups = [];

        foreach (self::originals() as [$original]) {
            $class = $original::class;

            $reflection = new ReflectionClass($class);
            if (!$reflection->isFinal()) {
                continue;
            }

            $groups[$class][] = $original;
        }

        foreach ($groups as $class => $objects) {
            foreach (array_chunk($objects, length: 16) as $batch => $values) {
                yield $class . ' batch ' . $batch => [$values, null, JsonType::array($class)];
            }
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>, string|null, JsonType<list<mixed>>}>
     */
    public static function shapes(): iterable
    {
        $first = new ConstructorlessPublicProperties();
        $first->string = 'first';
        $first->integer = 42;
        $first->boolean = true;
        $first->nullable = 'present';
        $second = new ConstructorlessPublicProperties();
        $second->string = 'second';
        $second->float = 1.5;

        yield 'cached public fields keep values and defaults independent' => [
            [$first, $second, new ConstructorlessPublicProperties()],
            null,
            JsonType::array(ConstructorlessPublicProperties::class),
        ];

        yield from RootArrayInputs::nested(
            Person::class,
            [new Person('Ada', 'Lovelace'), new Person('Grace', 'Hopper')],
            'people',
        );
        yield from RootArrayInputs::nested(EmptyObject::class, [new EmptyObject(), new EmptyObject()], 'empty objects');

        foreach ([1, 2, 3, 6, 63] as $depth) {
            yield 'empty root array depth ' . $depth => [[], null, RootArrayInputs::target(EmptyObject::class, $depth)];
        }

        foreach ([' ', "\t", "\r", "\n", " \t\r\n"] as $whitespace) {
            yield 'root array whitespace ' . bin2hex($whitespace) => [
                [new EmptyObject()],
                $whitespace . '[{}]' . $whitespace,
                JsonType::array(EmptyObject::class),
            ];
        }
    }

    /**
     * @return iterable<string, array{0: object, 1?: string}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function originals(): iterable
    {
        yield from RoundTripCases::objects();
        yield from RoundTripCases::scalarFields();
        yield from RoundTripCases::numericBoundaries();
        yield from RoundTripCases::stringFields();
        yield from PublicPropertyUnionRoundTripCases::objects();
    }
}
