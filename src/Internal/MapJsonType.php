<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use JsonException;
use Override;
use ReflectionException;

/**
 * @internal
 * @template T
 * @extends JsonType<ArrayObject<string, T>>
 */
final readonly class MapJsonType extends JsonType
{
    private MapType $collection;

    /** @param JsonType<T> $valueType */
    public function __construct(
        private JsonType $valueType,
    ) {
        $this->collection = new MapType($valueType->collectionItem(), true);
    }

    /**
     * @return ArrayObject<string, T>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    #[Override]
    public function decodeValue(mixed $value, string $path = ''): ArrayObject|DecodeError
    {
        /** @var ArrayObject<string, T>|DecodeError */
        return CollectionValueConverter::convert($this->itemClass(), $path, $this->collection, $value);
    }

    #[Override]
    public function collectionItem(): NestedCollectionType
    {
        return new NestedCollectionType($this->collection, false);
    }

    /** @return class-string */
    #[Override]
    public function itemClass(): string
    {
        return $this->valueType->itemClass();
    }
}
