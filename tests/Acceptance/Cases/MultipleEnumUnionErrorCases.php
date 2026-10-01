<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MultipleEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class MultipleEnumUnionErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        $target = new MultipleEnumUnionField(StringBackedStatus::Ready);
        $message =
            'Could not create '
            . $target::class
            . ' from the JSON object: Field value uses multiple enum types: '
            . IntBackedStatus::class
            . ', '
            . StringBackedStatus::class
            . '. Union declarations may contain at most one enum because selecting among multiple enums requires additional rules.';

        foreach ([
            'first enum backing value' => '{"value":"ready"}',
            'second enum backing value' => '{"value":1}',
            'unknown string backing value' => '{"value":"unknown"}',
            'unknown integer backing value' => '{"value":2}',
            'null value' => '{"value":null}',
            'object value' => '{"value":{}}',
            'field absent' => '{}',
        ] as $name => $json) {
            yield 'multiple enum union rejects ' . $name => [$json, $target::class, $message, 3];
        }
    }
}
