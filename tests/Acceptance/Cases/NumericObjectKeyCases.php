<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;

/** @internal */
final class NumericObjectKeyCases
{
    /** @return iterable<string, array{string, class-string, string, DecodeErrorKind}> */
    public static function mismatches(): iterable
    {
        yield 'numeric object keys with coercible scalar values' => [
            '{"0":42,"1":"42","2":"3.25","3":1}',
            ScalarFields::class,
            'Could not create '
                . ScalarFields::class
                . ' from the JSON object: Too few arguments to function '
                . ScalarFields::class
                . '::__construct(), 0 passed and exactly 4 expected',
            DecodeErrorKind::InvalidValue,
        ];
    }
}
