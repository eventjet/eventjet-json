<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObjectFields;

/** @internal */
final class EmptyObjectShapeCases
{
    /** @return iterable<string, array{object, string}> */
    public static function objects(): iterable
    {
        yield 'empty concrete root preserves object shape' => [new EmptyObject(), '{}'];
        yield 'empty objects, lists, and maps remain distinct' => [
            new EmptyObjectFields(),
            '{"object":{},"list":[],"map":{},"publicObject":{},"publicList":[],"publicMap":{}}',
        ];

        foreach (['named', '01', '+1', '1.0', '1e0', '-0'] as $key) {
            $fields = new EmptyObjectFields(list: [new EmptyObject()], map: new ArrayObject([
                $key => new EmptyObject(),
            ]));
            $fields->publicList = [new EmptyObject()];
            $fields->publicMap = new ArrayObject([$key => new EmptyObject()]);

            yield 'empty objects inside collections with key ' . $key => [
                $fields,
                '{"object":{},"list":[{}],"map":{"'
                    . $key
                    . '":{}},"publicObject":{},"publicList":[{}],"publicMap":{"'
                    . $key
                    . '":{}}}',
            ];
        }
    }

    /** @return iterable<string, array{string, class-string, string, int}> */
    public static function errors(): iterable
    {
        yield 'empty array cannot become an empty concrete root' => [
            '[]',
            EmptyObject::class,
            'Expected the JSON root to be an object, got array.',
            2,
        ];

        foreach (['object', 'publicObject', 'list', 'publicList', 'map', 'publicMap'] as $field) {
            [$json, $path] = match ($field) {
                'list', 'publicList' => ['[[]]', $field . '[0]'],
                'map', 'publicMap' => ['{"01":[]}', $field . '[01]'],
                default => ['[]', $field],
            };

            yield 'empty array cannot become an empty object in ' . $field => [
                '{"' . $field . '":' . $json . '}',
                EmptyObjectFields::class,
                'Could not create '
                    . EmptyObjectFields::class
                    . ' from the JSON object: Field '
                    . $path
                    . ' must be of type '
                    . EmptyObject::class
                    . ', array given.',
                3,
            ];
        }
    }
}
