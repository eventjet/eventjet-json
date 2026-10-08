<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use JsonException;
use Override;
use ReflectionException;

/**
 * @internal
 * @template-covariant T
 * @extends JsonType<list<T>>
 */
final readonly class ArrayJsonType extends JsonType
{
    private ListType $collection;

    /** @param JsonType<T> $itemType */
    public function __construct(
        private JsonType $itemType,
    ) {
        $this->collection = new ListType($itemType->collectionItem(), false);
    }

    /**
     * @return list<T>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    #[Override]
    public function decodeValue(mixed $value, string $path = ''): array|DecodeError
    {
        /** @var list<T>|DecodeError */
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
        return $this->itemType->itemClass();
    }
}
