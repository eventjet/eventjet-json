<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;

use function array_reverse;

/** @internal */
final class RootArrayInputs
{
    /**
     * @param class-string $class
     * @return JsonType<list<mixed>>
     */
    public static function target(string $class, int $depth): JsonType
    {
        if ($depth <= 1) {
            return JsonType::array($class);
        }

        return JsonType::array(self::target($class, $depth - 1));
    }

    public static function wrap(string $item, int $depth): string
    {
        for ($level = 0; $level < $depth; ++$level) {
            $item = '[' . $item . ']';
        }

        return $item;
    }

    /**
     * @param class-string $class
     * @param list<object> $objects
     * @return iterable<string, array{list<mixed>, string|null, JsonType<list<mixed>>}>
     */
    public static function nested(string $class, array $objects, string $name): iterable
    {
        $values = $objects;

        for ($depth = 1; $depth <= 3; ++$depth) {
            yield $name . ' depth ' . $depth => [$values, null, self::target($class, $depth)];
            $values = [[], $values, array_reverse($values)];
        }
    }
}
