<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\FinalClassMapFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class FinalClassMapErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'final class map constructor field rejects a scalar value' => [
                '{"people":{"author":"Ada"}}',
                'people[author]',
                'string',
            ],
            'final class map constructor field rejects an array value' => [
                '{"people":{"author":[]}}',
                'people[author]',
                'array',
            ],
            'final class map public property rejects a scalar value' => [
                '{"people":{},"publicPeople":{"author":"Ada"}}',
                'publicPeople[author]',
                'string',
            ],
            'final class map constructor field reports the failing key' => [
                '{"people":{"author":{"firstName":"Ada","lastName":"Lovelace"},"reviewer":"Grace"}}',
                'people[reviewer]',
                'string',
            ],
        ] as $name => [$json, $path, $actualType]) {
            yield $name => [
                $json,
                FinalClassMapFields::class,
                'Could not create '
                    . FinalClassMapFields::class
                    . ' from the JSON object: Field '
                    . $path
                    . ' must be of type '
                    . Person::class
                    . ', '
                    . $actualType
                    . ' given.',
                3,
            ];
        }

        yield 'final class map constructor field rejects an invalid nested value' => [
            '{"people":{"author":{"firstName":42,"lastName":"Lovelace"}}}',
            FinalClassMapFields::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field firstName must be of type string, int given.',
            3,
        ];

        yield 'final class map public property rejects an invalid nested value' => [
            '{"people":{},"publicPeople":{"author":{"firstName":"Ada","lastName":42}}}',
            FinalClassMapFields::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field lastName must be of type string, int given.',
            3,
        ];
    }
}
