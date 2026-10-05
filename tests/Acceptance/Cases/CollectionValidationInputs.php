<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BoolCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\FloatCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\IntCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntEnumCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ObjectCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringEnumCollectionValidationFields;

use function sprintf;

/** @internal */
final class CollectionValidationInputs
{
    /** @return array<string, object> */
    public static function fixtures(): array
    {
        return [
            'Strings' => new StringCollectionValidationFields(),
            'Integers' => new IntCollectionValidationFields(),
            'Floats' => new FloatCollectionValidationFields(),
            'Booleans' => new BoolCollectionValidationFields(),
            'StringEnums' => new StringEnumCollectionValidationFields(),
            'IntEnums' => new IntEnumCollectionValidationFields(),
            'Objects' => new ObjectCollectionValidationFields(),
        ];
    }

    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        foreach (self::fixtures() as $name => $fixture) {
            yield $name . ' collection validation baseline' => [$fixture];
        }
    }

    /** @return iterable<string, array{string, string, array<array-key, string>}> */
    public static function itemTypes(): iterable
    {
        $scalars = [
            'Strings' => ['string', '"ready"', ['1' => 'int', '1.25' => 'float', 'true' => 'bool']],
            'Integers' => ['int', '1', ['"1"' => 'string', '1.0' => 'float', 'true' => 'bool']],
            'Floats' => ['float', '1.25', ['"1.25"' => 'string', 'true' => 'bool']],
            'Booleans' => ['bool', 'true', ['"true"' => 'string', '1' => 'int', '1.0' => 'float']],
            'Objects' => [
                Person::class,
                '{"firstName":"Ada","lastName":"Lovelace"}',
                ['"Ada"' => 'string', '1' => 'int', '1.25' => 'float', 'true' => 'bool'],
            ],
        ];

        foreach ($scalars as $name => [$type, $valid, $invalid]) {
            $reasons = [];

            foreach ($invalid + ['null' => 'null', '[]' => 'array', '[1]' => 'array'] as $json => $actual) {
                $reasons[(string) $json] = sprintf(' must be of type %s, %s given.', $type, $actual);
            }

            if ($name !== 'Objects') {
                $reasons['{}'] = sprintf(' must be of type %s, stdClass given.', $type);
                $reasons['{"0":1}'] = sprintf(' must be of type %s, stdClass given.', $type);
            }

            yield $name => [$type, $valid, $reasons];
        }

        yield from self::enumTypes();
    }

    /** @return iterable<string, array{string, string, array<array-key, string>}> */
    private static function enumTypes(): iterable
    {
        foreach ([
            'StringEnums' => [
                StringBackedStatus::class,
                'string',
                '"ready"',
                ['1' => 'int', '1.0' => 'float', 'true' => 'bool'],
            ],
            'IntEnums' => [IntBackedStatus::class, 'int', '1', ['"1"' => 'string', '1.0' => 'float', 'true' => 'bool']],
        ] as $name => [$type, $backing, $valid, $invalid]) {
            $reasons = [];

            foreach ($invalid + ['null' => 'null', '[]' => 'array', '{}' => 'stdClass'] as $json => $actual) {
                $reasons[(string) $json] = sprintf(
                    ' uses backed enum %s, which expects a %s backing value; %s given.',
                    $type,
                    $backing,
                    $actual,
                );
            }

            $unknown = $name === 'StringEnums' ? '"unknown"' : '2';
            $reasons[$unknown] = sprintf(
                ' uses backed enum %s, which has no case with backing value %s.',
                $type,
                $name === 'StringEnums' ? "'unknown'" : '2',
            );

            yield $name => [$type, $valid, $reasons];
        }
    }
}
