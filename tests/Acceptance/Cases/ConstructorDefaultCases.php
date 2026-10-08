<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\CollectionUnionDefaults;

/** @internal */
final class ConstructorDefaultCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'omitted collection union uses constructor default' => [
            '{}',
            (static function (): object {
                $object = new CollectionUnionDefaults();
                $object->map = null;
                return $object;
            })(),
        ];
    }
}
