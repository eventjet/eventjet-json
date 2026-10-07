<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use function count;

/** @internal */
final class RootMapShapeCases
{
    /**
     * @return iterable<string, array{list<mixed>|object, null, JsonType<list<mixed>|object>}>
     * @throws RuntimeException
     */
    public static function values(): iterable
    {
        yield from self::shapes(EmptyObject::class, [new EmptyObject()]);
        yield from self::shapes(StringBackedStatus::class, StringBackedStatus::cases());
        yield from self::shapes(IntBackedStatus::class, IntBackedStatus::cases());
    }

    /**
     * @param class-string $class
     * @param non-empty-list<object> $items
     * @return iterable<string, array{list<mixed>|object, null, JsonType<list<mixed>|object>}>
     * @throws RuntimeException
     */
    private static function shapes(string $class, array $items): iterable
    {
        $values = [];
        foreach (['01', '1e0', '-01', 'a.b', '', 'a"b', "line\nkey", 'é', 'primary'] as $index => $key) {
            $values[$key] = $items[$index % count($items)] ?? throw new RuntimeException('Missing generated item.');
        }
        $map = new ArrayObject($values);
        yield $class . ' keys' => [$map, null, JsonType::map($class)];
        yield $class . ' empty' => [new ArrayObject(), null, JsonType::map($class)];
        yield $class . ' map of maps' => [
            new ArrayObject(['first' => new ArrayObject(), 'next' => $map]),
            null,
            JsonType::map(JsonType::map($class)),
        ];
        yield $class . ' map of lists' => [
            new ArrayObject(['empty' => [], 'values' => $items]),
            null,
            JsonType::map(JsonType::array($class)),
        ];
        yield $class . ' list of maps' => [
            [new ArrayObject(), $map],
            null,
            JsonType::array(JsonType::map($class)),
        ];
        $original = $map;
        $target = JsonType::map($class);
        foreach ([1, 2, 3, 4, 5, 6] as $depth) {
            $original = self::branch($original);
            $target = self::branchType($target);
            yield $class . ' alternating depth ' . $depth => [$original, null, $target];
        }
    }

    /** @return ArrayObject<string, list<object>> */
    private static function branch(object $original): ArrayObject
    {
        /** @var array<string, list<object>> $values */
        $values = ['empty' => [], 'branch' => [$original, new ArrayObject()]];
        return new ArrayObject($values);
    }

    /**
     * @param JsonType<object> $target
     * @return JsonType<object>
     */
    private static function branchType(JsonType $target): JsonType
    {
        return JsonType::map(JsonType::array($target));
    }
}
