<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class StringValueRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'Unicode' => [new Person('Grüße, 世界, 😀, é', 'Grüße, 世界, 😀, é', 'Grüße, 世界, 😀, é')];
        yield 'escaped characters' => [new Person('"\/

	' . "\x00" . '', '"\/

	' . "\x00" . '', '"\/

	' . "\x00" . '')];
        yield 'scientific notation text' => [new Person('1e3', '1e3', '1e3')];
        yield 'leading zeros' => [new Person('00042', '00042', '00042')];
    }
}
