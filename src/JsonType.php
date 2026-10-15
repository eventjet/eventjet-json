<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ArrayObject;
use Eventjet\Json\Internal\RootDescriptor;

use function is_string;

/**
 * @api
 * @template-covariant T
 */
final readonly class JsonType
{
    /** @param RootDescriptor<T> $descriptor */
    private function __construct(
        private RootDescriptor $descriptor,
    ) {}

    /**
     * @template TItem
     * @param class-string<TItem&object>|self<TItem> $itemType
     * @phpstan-param (TItem is object ? class-string<TItem> : never)|self<TItem> $itemType
     * @psalm-param class-string<TItem&object>|self<TItem> $itemType
     * @return self<list<TItem>>
     */
    public static function array(string|self $itemType): self
    {
        /** @var RootDescriptor<list<TItem>> $descriptor */
        $descriptor = self::wrap('array', $itemType);
        return new self($descriptor);
    }

    /**
     * @template TValue
     * @param class-string<TValue&object>|self<TValue> $valueType
     * @phpstan-param (TValue is object ? class-string<TValue> : never)|self<TValue> $valueType
     * @psalm-param class-string<TValue&object>|self<TValue> $valueType
     * @return self<ArrayObject<string, TValue>>
     */
    public static function map(string|self $valueType): self
    {
        /** @var RootDescriptor<ArrayObject<string, TValue>> $descriptor */
        $descriptor = self::wrap('object', $valueType);
        return new self($descriptor);
    }

    /**
     * @template TItem
     * @param 'array'|'object' $shape
     * @param class-string<TItem&object>|self<TItem> $item
     * @return RootDescriptor<mixed>
     */
    private static function wrap(string $shape, string|self $item): RootDescriptor
    {
        return (
            is_string($item)
                ? new RootDescriptor([$shape], $item)
                : new RootDescriptor([$shape, ...$item->descriptor->shapes], $item->descriptor->itemClass)
        );
    }
}
