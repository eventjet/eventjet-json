<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ArrayObject;
use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Internal\Node\Node;
use JsonException;
use ReflectionException;

use function is_string;

/**
 * @api
 * @template-covariant T
 */
abstract readonly class JsonType
{
    /**
     * @template TItem
     * @param class-string<TItem&object>|self<TItem> $itemType
     * @phpstan-param (TItem is object ? class-string<TItem> : never)|self<TItem> $itemType
     * @psalm-param class-string<TItem&object>|self<TItem> $itemType
     * @return self<list<TItem>>
     */
    public static function array(string|self $itemType): self
    {
        return new ArrayJsonType(self::resolve($itemType));
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
        return new MapJsonType(self::resolve($valueType));
    }

    /**
     * @template TValue
     * @param class-string<TValue&object>|self<TValue> $type
     * @phpstan-param (TValue is object ? class-string<TValue> : never)|self<TValue> $type
     * @psalm-param class-string<TValue&object>|self<TValue> $type
     * @return self<TValue>
     */
    private static function resolve(string|self $type): self
    {
        return is_string($type) ? new ClassJsonType($type) : $type;
    }

    /**
     * Compiles this descriptor. Errors name $class, the item class of the root descriptor.
     *
     * @internal
     * @param class-string $class
     * @throws ReflectionException
     */
    abstract public function node(string $class): Node;

    /**
     * @internal
     * @return T|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    abstract public function decode(mixed $value): mixed;

    /**
     * @internal
     * @return 'array'|'object'
     */
    abstract public function rootShape(): string;

    /**
     * @internal
     * @return class-string
     */
    abstract public function itemClass(): string;
}
