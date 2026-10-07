<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use ReflectionException;
use RuntimeException;

use function bin2hex;

/** @internal */
final class RootMapRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>|object, string|null, JsonType<list<mixed>|object>}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function values(): iterable
    {
        foreach (RootArrayRoundTripCases::objects() as $name => [$objects]) {
            $values = [];
            foreach ($objects as $index => $object) {
                $values['item-' . $index] = $object;
            }
            $first = $objects[0] ?? throw new RuntimeException('Empty generated batch.');
            yield 'generated map ' . $name => [new ArrayObject($values), null, JsonType::map($first::class)];
        }

        yield from RootMapShapeCases::values();

        foreach ([' ', "\t", "\r", "\n", " \t\r\n"] as $whitespace) {
            yield 'map whitespace ' . bin2hex($whitespace) => [
                new ArrayObject(['value' => new EmptyObject()]),
                $whitespace . '{"value":{}}' . $whitespace,
                JsonType::map(EmptyObject::class),
            ];
        }
    }
}
