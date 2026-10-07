<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;

/** @internal */
final class NestedShapeInputs
{
    /** @return array{string, string, string, string, string} */
    public static function shapes(): array
    {
        return ['list', 'non-empty-list', 'non-empty-array', 'ArrayObject', 'tuple'];
    }

    public static function declaration(string $shape, string $item): string
    {
        return $shape === 'tuple' ? 'array{0: ' . $item . '}' : NestedCollectionInputs::declaration($shape, $item);
    }

    /** @return array<array-key, mixed>|ArrayObject<string, mixed> */
    public static function wrap(string $shape, mixed $value): array|ArrayObject
    {
        return match ($shape) {
            'tuple', 'list', 'non-empty-list' => [$value],
            'ArrayObject' => new ArrayObject(['a.b' => $value]),
            default => ['a.b' => $value],
        };
    }

    public static function path(string $shape): string
    {
        return $shape === 'tuple' || $shape === 'list' || $shape === 'non-empty-list' ? '[0]' : '["a.b"]';
    }
}
