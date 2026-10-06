<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function sprintf;

/** @internal */
final class ListValueConverter
{
    /**
     * @param class-string $class
     * @return list<bool|float|int|object|string>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ListType $collection,
        mixed $value,
    ): array|DecodeError {
        $itemType = $collection->itemType;

        $expectedType = sprintf('%s<%s>', $collection->nonEmpty ? 'non-empty-list' : 'list', $itemType);
        $value = ListInputNormalizer::normalize($class, $field, $expectedType, $collection, $value);

        if ($value instanceof DecodeError) {
            return $value;
        }

        $converted = [];
        $index = 0;

        while (array_key_exists($index, $value)) {
            $convertedItem = CollectionItemValueConverter::convert(
                $class,
                sprintf('%s[%d]', $field->getName(), $index),
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
