<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor;
use RuntimeException;

/** @internal */
final class RootArrayConstructionCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, JsonType<list<mixed>>, string, int, RuntimeException}>
     */
    public static function exceptions(): iterable
    {
        foreach ([1, 2, 3, 6] as $depth) {
            yield 'constructor exception depth ' . $depth => [
                RootArrayInputs::wrap('{"value":"rejected"}', $depth),
                RootArrayInputs::target(ThrowingConstructor::class, $depth),
                'Could not create '
                    . ThrowingConstructor::class
                    . ' from the JSON object: The constructor rejected the decoded value.',
                3,
                ThrowingConstructor::exception(),
            ];
        }
    }
}
