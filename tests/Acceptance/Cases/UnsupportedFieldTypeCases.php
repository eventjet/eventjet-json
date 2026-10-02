<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use DatePeriod;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;
use Eventjet\Json\Test\Acceptance\Fixtures\ObjectField;
use Eventjet\Json\Test\Acceptance\Fixtures\ObjectPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StdClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\UnionPublicProperty;
use stdClass;

use function is_string;

/** @internal */
final class UnsupportedFieldTypeCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'mixed' => [new MixedField('anything'), 'value', '"anything"', 'uses unsupported type mixed'],
            'object' => [new ObjectField(new stdClass()), 'value', '{}', 'uses unsupported type object'],
            'stdClass' => [new StdClassField(new stdClass()), 'value', '{}', 'uses unsupported type stdClass'],
            'untyped' => [DatePeriod::class, 'start', '"anything"', 'uses no type declaration'],
        ] as $name => [$target, $field, $value, $declaration]) {
            $class = is_string($target) ? $target : $target::class;
            $message =
                'Could not create '
                . $class
                . ' from the JSON object: Field '
                . $field
                . ' '
                . $declaration
                . '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.';

            yield $name . ' field, member present' => ['{"' . $field . '":' . $value . '}', $class, $message, 3];
            yield $name . ' field, member absent' => ['{}', $class, $message, 3];
        }

        foreach ([
            'object public property' => [new ObjectPublicProperty(), '{}', 'object'],
            'union public property' => [UnionPublicProperty::class, '"value"', 'string|int'],
        ] as $name => [$target, $value, $type]) {
            $class = is_string($target) ? $target : $target::class;

            yield $name => [
                '{"value":' . $value . '}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses unsupported public property type '
                    . $type
                    . '. Public properties outside the constructor currently support declared scalar, array, and backed enum types only.',
                3,
            ];
        }
    }
}
