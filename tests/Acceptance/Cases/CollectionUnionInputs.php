<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;

use function in_array;

/** @internal */
final class CollectionUnionInputs
{
    /**
     * @param list<bool|float|int|string|object|null> $values
     * @return iterable<int, array<array-key, bool|float|int|string|object|null>|ArrayObject<string, bool|float|int|string|object|null>>
     */
    public static function collections(string $shape, array $values): iterable
    {
        foreach ($values as $value) {
            yield self::collection($shape, [$value]);
        }
        if (in_array($shape, ['list', 'non-empty-list', 'map', 'ArrayObject'], strict: true)) {
            yield self::collection($shape, $values);
        }
        if (in_array($shape, ['list', 'optional tuple', 'ArrayObject'], strict: true)) {
            yield self::collection($shape, []);
        }
    }

    /**
     * @param list<bool|float|int|string|object|null> $values
     * @return array<array-key, bool|float|int|string|object|null>|ArrayObject<string, bool|float|int|string|object|null>
     */
    private static function collection(string $shape, array $values): array|ArrayObject
    {
        $map = [];
        foreach ($values as $index => $value) {
            $map[$index === 0 ? '01' : 'key' . $index] = $value;
        }
        return match ($shape) {
            'map' => $map,
            'ArrayObject' => new ArrayObject($map),
            default => $values,
        };
    }
}
