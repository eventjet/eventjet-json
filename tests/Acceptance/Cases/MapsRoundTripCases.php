<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class MapsRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>|object, callable(): JsonType<list<mixed>|object>}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'list of maps' => [
            [new ArrayObject(['author' => new Person('Ada', 'Lovelace')])],
            static fn(): JsonType => JsonType::array(JsonType::map(Person::class)),
        ];
    }
}
