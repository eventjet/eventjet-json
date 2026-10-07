<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use Override;
use ReflectionException;

use function array_key_exists;
use function sprintf;

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
     * @throws ReflectionException
     */
    #[Override]
    public function decodeValue(mixed $value, string $path = ''): array|DecodeError
    {
        $class = $this->itemClass();
        $values = ListInputNormalizer::normalize(
            $class,
            $path,
            (string) $this->collectionItem(),
            $this->collection,
            $value,
        );

        if ($values instanceof DecodeError) {
            return $values;
        }

        $converted = [];
        $index = 0;

        while (array_key_exists($index, $values)) {
            $convertedItem = $this->itemType->decodeValue($values[$index], sprintf('%s[%d]', $path, $index));

            if ($convertedItem instanceof DecodeError) {
                return $convertedItem;
            }

            $converted[] = $convertedItem;
            ++$index;
        }

        return $converted;
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
