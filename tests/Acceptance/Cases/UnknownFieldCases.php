<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class UnknownFieldCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, NestedObjectFields|Person}>
     */
    public static function objects(): iterable
    {
        $person = new Person('Ada', 'Lovelace', null, 36);

        yield 'unknown scalar fields' => [
            '{"extraNull":null,"extraBoolean":true,"extraInteger":42,"extraFloat":3.25,"extraString":"ignored","firstName":"Ada","lastName":"Lovelace","middleName":null,"age":36}',
            $person,
        ];
        yield 'unknown structured fields with unusual names' => [
            '{"":[],"0":[1,2],"first_name":{"nested":"value"},"zusätzlich":{"nested":[true,null]},"firstName":"Ada","lastName":"Lovelace","middleName":null,"age":36}',
            $person,
        ];

        yield 'unknown fields at every object depth' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace","middleName":null,"age":36,"occupation":"mathematician"},"address":{"city":"London","coordinates":{"latitude":51.507351,"longitude":-0.127758,"referenceSystem":"WGS 84"},"country":"United Kingdom"},"alternate":null,"source":"example"}',
            new NestedObjectFields($person, new Address('London', new Coordinates(51.507_351, -0.127_758)), null),
        ];
    }
}
