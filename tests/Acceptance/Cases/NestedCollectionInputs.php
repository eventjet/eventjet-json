<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class NestedCollectionInputs
{
    /** @return iterable<string, list<bool|float|int|object|string|null>> */
    public static function domains(): iterable
    {
        yield 'int' => [PHP_INT_MIN, 0, PHP_INT_MAX];
        yield 'float' => [0.0, 3.0, -1.25];
        yield 'string' => ['', '42', "雪\n"];
        yield 'bool' => [true, false];
        yield 'non-zero-int' => [-1, 0, 1];
        yield '\\' . Coordinates::class => [new Coordinates(3.0, 4.5)];
        yield '\\' . StringBackedStatus::class => [StringBackedStatus::Ready, StringBackedStatus::Pending];
        yield '\\' . IntBackedStatus::class => [IntBackedStatus::Ready, IntBackedStatus::Pending];
        yield '\\'
            . Coordinates::class
            . '|\\'
            . StringBackedStatus::class
            . '|\\'
            . StringBackedOutcome::class
            . '|int|bool|null' => [
            new Coordinates(3.0, 4.5),
            StringBackedStatus::Ready,
            StringBackedOutcome::Failed,
            42,
            true,
            false,
            null,
        ];
    }

    /** @return iterable<array{string, string}> */
    public static function pairs(string $leaf): iterable
    {
        $inners = ['list', 'non-empty-list', 'non-empty-array', 'ArrayObject'];
        foreach (['list', 'non-empty-list', 'non-empty-array', 'ArrayObject', 'non-empty-map'] as $index => $outer) {
            $innerShape = match ($index % 4) {
                0 => 'list',
                1 => 'non-empty-list',
                2 => 'non-empty-array',
                default => 'ArrayObject',
            };
            foreach ($leaf === 'int' ? $inners : [$innerShape] as $inner) {
                yield [$outer, $inner];
            }
        }
    }

    public static function declaration(string $shape, string $item): string
    {
        return $shape . '<' . ($shape === 'list' || $shape === 'non-empty-list' ? '' : 'string, ') . $item . '>';
    }

    /** @param list<array<array-key, mixed>|bool|float|int|object|string|null> $items
     * @return list<array<array-key, mixed>|bool|float|int|object|string|null>|array<string, array<array-key, mixed>|bool|float|int|object|string|null>|ArrayObject<string, array<array-key, mixed>|bool|float|int|object|string|null>
     */
    public static function wrap(string $shape, array $items): array|ArrayObject
    {
        if ($shape === 'list' || $shape === 'non-empty-list') {
            return $items;
        }
        $map = [];
        foreach ($items as $index => $value) {
            $map[$index === 0 ? '01' : 'key.' . $index] = $value;
        }
        return $shape === 'ArrayObject' ? new ArrayObject($map) : $map;
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function fixture(string $shape, string $type, string $tag, mixed $value): object
    {
        $class = CollectionDeclarationFixture::create(
            $shape === 'ArrayObject' ? '\\ArrayObject' : 'array',
            $type,
            $tag,
        );
        $reflection = new ReflectionClass($class);
        $object = $tag === 'param' ? $reflection->newInstance($value) : $reflection->newInstance();
        if ($tag === 'var') {
            $reflection->getProperty('value')->setValue($object, $value);
        }
        return $object;
    }
}
