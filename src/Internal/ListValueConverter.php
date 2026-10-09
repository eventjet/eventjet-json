<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use stdClass;

use function array_key_exists;
use function class_exists;
use function enum_exists;
use function in_array;
use function is_string;

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
        $value = ListInputNormalizer::normalize($class, $path, $collection, $value);

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
        if ($value === []) {
            return [];
        }
        if (is_string($itemType) && !enum_exists($itemType) && class_exists($itemType)) {
            return self::convertObjects($class, $path, $itemType, $value);
        }
        $converted = [];
        $index = 0;

        while (array_key_exists($index, $value)) {
            $convertedItem = CollectionItemValueConverter::convert(
                $class,
                $path . '[' . $index . ']',
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

    /**
     * @param class-string $class
     * @param class-string $itemType
     * @param list<mixed> $values
     * @return list<object>|DecodeError
     */
    private static function convertObjects(
        string $class,
        string $path,
        string $itemType,
        array $values,
    ): array|DecodeError {
        $converted = [];
        /** @var mixed $value */
        foreach ($values as $index => $value) {
            $itemPath = $path . '[' . $index . ']';
            if (!$value instanceof stdClass) {
                return DecodeError::fieldTypeMismatch($class, $itemPath, $itemType, $value);
            }
            $object = ObjectHydrator::hydrate($itemType, $value, $itemPath);
            if ($object instanceof DecodeError) {
                return $object;
            }
            $converted[] = $object;
        }
        return $converted;
    }
}
