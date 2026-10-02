<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\FinalClassListFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

/** @internal */
final class FinalClassListErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'constructor field rejects a scalar item' => [
                '{"people":["Ada"]}',
                'people[0]',
                'string',
            ],
            'constructor field rejects an array item' => [
                '{"people":[[]]}',
                'people[0]',
                'array',
            ],
            'public property rejects a scalar item' => [
                '{"people":[],"publicPeople":["Ada"]}',
                'publicPeople[0]',
                'string',
            ],
            'constructor field reports a nonzero failing index' => [
                '{"people":[{"firstName":"Ada","lastName":"Lovelace"},"Grace"]}',
                'people[1]',
                'string',
            ],
        ] as $name => [$json, $path, $actualType]) {
            yield $name => [
                $json,
                FinalClassListFields::class,
                'Could not create '
                    . FinalClassListFields::class
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

        yield 'constructor field rejects an invalid nested value' => [
            '{"people":[{"firstName":42,"lastName":"Lovelace"}]}',
            FinalClassListFields::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field firstName must be of type string, int given.',
            3,
        ];

        yield 'public property rejects an invalid nested value' => [
            '{"people":[],"publicPeople":[{"firstName":"Ada","lastName":42}]}',
            FinalClassListFields::class,
            'Could not create '
                . Person::class
                . ' from the JSON object: Field lastName must be of type string, int given.',
            3,
        ];
    }
}
