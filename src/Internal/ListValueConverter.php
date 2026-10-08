<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

use function array_key_exists;
use function in_array;
use function sprintf;

/** @internal */
final class ListValueConverter
{
    /**
     * @param class-string $class
     * @return list<mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(string $class, string $path, ListType $collection, mixed $value): array|DecodeError
    {
        $itemType = $collection->itemType;

        $expectedType = sprintf('%s<%s>', $collection->nonEmpty ? 'non-empty-list' : 'list', $itemType);
        $value = ListInputNormalizer::normalize($class, $path, $expectedType, $collection, $value);

        if ($value instanceof DecodeError) {
            return $value;
        }

        return in_array($itemType, ['string', 'int', 'bool', 'float'], strict: true)
            ? ScalarListValueConverter::convert($class, $path, $itemType, $value)
            : self::convertItems($class, $path, $itemType, $value);
    }

    /**
     * @param class-string $class
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function convertItems(
        string $class,
        string $path,
        string|CollectionUnionType|NestedCollectionType $itemType,
        array $value,
    ): array|DecodeError {
        $converted = [];
        $index = 0;

        while (array_key_exists($index, $value)) {
            $convertedItem = CollectionItemValueConverter::convert(
                $class,
                sprintf('%s[%d]', $path, $index),
                $itemType,
                $value[$index],
            );

            if ($convertedItem instanceof DecodeError) {
                return $convertedItem;
            }

            $converted[] = $convertedItem;
            ++$index;
        }

        return $converted;
    }
}
