<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use JsonException;
use Override;
use ReflectionException;

use function array_key_exists;
use function array_keys;
use function assert;

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
        $values = MapInputNormalizer::normalize($this->itemClass(), $path, $this->collection, $value);

        if ($values instanceof DecodeError) {
            return $values;
        }

        $converted = [];
        foreach (array_keys($values) as $key) {
            assert(array_key_exists($key, $values), description: 'A key returned by array_keys() must exist.');
            $convertedValue = $this->valueType->decodeValue($values[$key], FieldPath::key($path, $key));

            if ($convertedValue instanceof DecodeError) {
                return $convertedValue;
            }

            $converted[$key] = $convertedValue;
        }

        return new ArrayObject($converted);
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
