<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties;

use function str_repeat;

/** @internal */
final class CachedPublicPropertyErrorCases
{
    /** @return iterable<string, array{string, JsonType<list<mixed>>, string, int}> */
    public static function errors(): iterable
    {
        foreach ([1, 2, 3] as $depth) {
            $path = str_repeat('[0]', $depth - 1) . '[1].integer';
            yield 'cached public fields still validate values at depth ' . $depth => [
                RootArrayInputs::wrap('[{}, {"integer":"wrong"}]', $depth - 1),
                RootArrayInputs::target(ConstructorlessPublicProperties::class, $depth),
                'Could not create '
                    . ConstructorlessPublicProperties::class
                    . ' from the JSON object: Field '
                    . $path
                    . ' must be of type int, string given.',
                3,
            ];
        }
    }
}
