<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use function sprintf;

/** @internal */
final class CollectionShapeErrors
{
    /** @return iterable<array-key, string> */
    public static function inputs(string $form, string $type, string $valid, string $field): iterable
    {
        yield from self::scalarInputs($form, $field);

        $expected = match ($form) {
            'list' => 'list<' . $type . '>',
            'nonEmptyList' => 'non-empty-list<' . $type . '>',
            default => 'JSON object',
        };

        foreach (self::wrongShapes($form, $valid) as $json => $actual) {
            yield $json => sprintf(' must be of type %s, %s given.', $expected, $actual);
        }

        if ($form === 'nonEmptyList') {
            yield '[]' => ' must be of type ' . $expected . ', empty list given.';
        }

        if ($form === 'map') {
            yield '{}' => ' uses non-empty-array<string, TValue> and cannot accept an empty JSON object. Use ArrayObject<string, TValue> when the map may be empty.';
        }
    }

    /** @return iterable<array-key, string> */
    private static function scalarInputs(string $form, string $field): iterable
    {
        $expected = $form === 'objectMap' ? 'JSON object' : 'array';

        foreach ([
            'null' => 'null',
            'true' => 'bool',
            '1' => 'int',
            '1.25' => 'float',
            '"text"' => 'string',
        ] as $json => $actual) {
            $reportedType = $field === 'objectMap' && $json === 'null' ? 'ArrayObject' : $expected;
            yield $json => sprintf(' must be of type %s, %s given.', $reportedType, $actual);
        }
    }

    /** @return iterable<array-key, string> */
    private static function wrongShapes(string $form, string $valid): iterable
    {
        if ($form === 'list' || $form === 'nonEmptyList') {
            yield '{}' => 'stdClass';
            yield '{"0":' . $valid . '}' => 'stdClass';
            yield '{"named":' . $valid . '}' => 'stdClass';
            return;
        }

        yield '[]' => 'array';
        yield '[' . $valid . ']' => 'array';
    }
}
