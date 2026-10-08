<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class JsonFormattingRoundTripCases
{
    /**
     * @return iterable<string, array{object, string}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'member order: age, middleName, lastName, firstName' => [
            new Person('Ada', 'Lovelace', 'Byron', 36),
            '{"age":36,"middleName":"Byron","lastName":"Lovelace","firstName":"Ada"}',
        ];
        yield 'object root whitespace: all' => [new Person('Ada', 'Lovelace'), ' 	
{"firstName":"Ada","lastName":"Lovelace","middleName":null,"age":null} 	
'];
    }
}
