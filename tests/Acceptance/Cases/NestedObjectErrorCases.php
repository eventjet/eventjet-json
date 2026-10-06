<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassEnumScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class NestedObjectErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'nonempty list' => '["Ada","Lovelace"]',
            'empty list' => '[]',
        ] as $name => $person) {
            yield 'nested object rejects a ' . $name => [
                '{"person":'
                    . $person
                    . ',"address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":null}',
                NestedObjectFields::class,
                'Could not create '
                    . NestedObjectFields::class
                    . ' from the JSON object: Field person must be of type '
                    . Person::class
                    . ', array given.',
                3,
            ];
        }

        yield 'non-nullable nested object rejects a scalar value' => [
            '{"person":"Ada","address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":null}',
            NestedObjectFields::class,
            'Could not create '
                . NestedObjectFields::class
                . ' from the JSON object: Field person must be of type '
                . Person::class
                . ', string given.',
            3,
        ];

        yield 'nullable nested object rejects a scalar value' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":"Charles"}',
            NestedObjectFields::class,
            'Could not create '
                . NestedObjectFields::class
                . ' from the JSON object: Field alternate must be of type '
                . Person::class
                . '|null, string given.',
            3,
        ];

        yield 'nested object after an omitted field rejects a scalar value' => [
            '{"address":"London","alternate":null}',
            NestedObjectFields::class,
            'Could not create '
                . NestedObjectFields::class
                . ' from the JSON object: Field address must be of type '
                . Address::class
                . ', string given.',
            3,
        ];

        yield 'nested object rejects an invalid field value' => [
            '{"person":{"firstName":42,"lastName":"Lovelace"},"address":{"city":"London","coordinates":{"latitude":51.5,"longitude":-0.1}},"alternate":null}',
            NestedObjectFields::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field person.firstName must be of type string, int given.',
            3,
        ];

        yield 'recursively nested object rejects an invalid field value' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"address":{"city":"London","coordinates":{"latitude":"north","longitude":-0.1}},"alternate":null}',
            NestedObjectFields::class,
            'Could not create '
                . Coordinates::class
                . ' from the JSON object: Field address.coordinates.latitude must be of type float, string given.',
            3,
        ];

        yield 'nested object public property rejects a scalar value' => [
            '{"person":"Ada"}',
            NestedObjectPublicProperties::class,
            'Could not create '
                . NestedObjectPublicProperties::class
                . ' from the JSON object: Field person must be of type '
                . Person::class
                . ', string given.',
            3,
        ];

        yield 'nested object public property rejects an invalid field value' => [
            '{"person":{"firstName":42,"lastName":"Lovelace"}}',
            NestedObjectPublicProperties::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field person.firstName must be of type string, int given.',
            3,
        ];

        yield 'class and scalar union rejects an invalid nested field value' => [
            '{"value":{"firstName":42,"lastName":"Lovelace"}}',
            ClassScalarUnionField::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field value.firstName must be of type string, int given.',
            3,
        ];

        yield 'class union public property rejects an invalid nested field value' => [
            '{"value":{"firstName":42,"lastName":"Lovelace"}}',
            ClassEnumScalarUnionPublicProperty::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field value.firstName must be of type string, int given.',
            3,
        ];

        $parentClassFieldTarget = new ParentClassField(
            new Person('Ada', 'Lovelace'),
            new ParentClassFieldBase(),
            'value',
        );

        yield 'non-final nested class is rejected' => [
            '{"person":{"firstName":"Ada","lastName":"Lovelace"},"value":{},"label":"value"}',
            $parentClassFieldTarget::class,
            'Could not create '
                . $parentClassFieldTarget::class
                . ' from the JSON object: Field value uses non-final class '
                . ParentClassFieldBase::class
                . '. Values may be subclasses, whose runtime class JSON does not identify.',
            3,
        ];
    }
}
