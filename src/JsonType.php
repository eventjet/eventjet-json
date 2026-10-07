<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\NestedCollectionType;
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
     * @phpstan-param (TItem is object ? class-string<TItem> : self<TItem>) $itemType
     * @psalm-param class-string<TItem&object>|self<TItem> $itemType
     * @return self<list<TItem>>
     */
    public static function array(string|self $itemType): self
    {
        if (is_string($itemType)) {
            return new ArrayJsonType(new ClassJsonType($itemType));
        }

        return new ArrayJsonType($itemType);
    }

    /**
     * @internal
     * @return T|DecodeError
     * @throws ReflectionException
     */
    abstract public function decodeValue(mixed $value, string $path = ''): mixed;

    /**
     * @internal
     * @return class-string|NestedCollectionType
     */
    abstract public function collectionItem(): string|NestedCollectionType;

    /**
     * @internal
     * @return class-string
     */
    abstract public function itemClass(): string;
}
