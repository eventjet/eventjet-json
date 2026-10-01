<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\MultipleClassUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\SelfClassUnionField;

/** @internal */
final class MultipleClassUnionErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        $target = new MultipleClassUnionField(new Person('Ada', 'Lovelace'));
        $message =
            'Could not create '
            . $target::class
            . ' from the JSON object: Field value uses multiple class types: '
            . Person::class
            . ', '
            . ScalarFields::class
            . '. JSON does not identify which class to instantiate.';

        foreach ([
            'first class-shaped object' => '{"value":{"firstName":"Ada","lastName":"Lovelace"}}',
            'second class-shaped object' => '{"value":{"string":"value","integer":42,"float":3.25,"boolean":true}}',
            'empty object' => '{"value":{}}',
            'scalar value' => '{"value":"value"}',
            'null value' => '{"value":null}',
            'field absent' => '{}',
        ] as $name => $json) {
            yield 'multiple class union rejects ' . $name => [$json, $target::class, $message, 3];
        }

        $selfTarget = new SelfClassUnionField(new Person('Ada', 'Lovelace'));

        yield 'multiple class union rejects self type' => [
            '{"value":{}}',
            $selfTarget::class,
            'Could not create '
                . $selfTarget::class
                . ' from the JSON object: Field value uses multiple class types: '
                . Person::class
                . ', self. JSON does not identify which class to instantiate.',
            3,
        ];
    }
}
