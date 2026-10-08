<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class ObjectHydrationRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'constructor-bound, direct, and inherited public properties' => [(static function (): object {
            $object = new PublicPropertiesWithConstructor('constructor value');
            $object->label = 'property value';
            $object->active = true;
            $object->inherited = 42;
            return $object;
        })()];
        yield 'nested final class public properties' => [(static function (): object {
            $object = new NestedObjectPublicProperties();
            $object->person = new Person('Ada', 'Lovelace');
            $object->alternate = new Person('Charles', 'Babbage');
            $object->child = (static function (): object {
                $object = new NestedObjectPublicProperties();
                $object->person = new Person('Grace', 'Hopper');
                $object->alternate = null;
                $object->child = null;
                return $object;
            })();
            return $object;
        })()];
        yield 'recursively nested readonly objects with a non-null field' => [new NestedObjectFields(
            new Person('Ada', 'Lovelace'),
            new Address('Paris', new Coordinates(48.856_613, 2.352_222)),
            new Person('Charles', 'Babbage'),
        )];
        yield 'backed enum fields: Ready, Pending' => [new BackedEnumFields(
            StringBackedStatus::Ready,
            StringBackedStatus::Ready,
            IntBackedStatus::Pending,
        )];
    }
}
