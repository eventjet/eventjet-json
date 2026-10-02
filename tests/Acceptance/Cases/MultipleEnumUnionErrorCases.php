<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus;
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
        $target = new OverlappingEnumUnionField(StringBackedStatus::Ready);
        $message =
            'Could not create '
            . $target::class
            . ' from the JSON object: Field value uses multiple backed enums '
            . OverlappingStringBackedStatus::class
            . ' and '
            . StringBackedStatus::class
            . " with overlapping backing value 'ready'. JSON cannot identify which enum case to instantiate.";

        foreach ([
            'overlapping backing value' => '{"value":"ready"}',
            'first enum unique backing value' => '{"value":"pending"}',
            'second enum unique backing value' => '{"value":"complete"}',
            'unknown string backing value' => '{"value":"unknown"}',
            'null value' => '{"value":null}',
            'object value' => '{"value":{}}',
            'field absent' => '{}',
        ] as $name => $json) {
            yield 'overlapping enum union rejects ' . $name => [$json, $target::class, $message, 3];
        }
    }
}
