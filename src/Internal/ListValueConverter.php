<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

use function array_key_exists;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
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

        if (in_array($itemType, ['string', 'int', 'bool', 'float'], strict: true)) {
            return self::convertScalars($class, $path, $itemType, $value);
        }

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

    /**
     * @param class-string $class
     * @param 'string'|'int'|'bool'|'float' $itemType
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    private static function convertScalars(
        string $class,
        string $path,
        string $itemType,
        array $value,
    ): array|DecodeError {
        $index = 0;
        while (array_key_exists($index, $value)) {
            $valid = match ($itemType) {
                'string' => is_string($value[$index]),
                'int' => is_int($value[$index]),
                'bool' => is_bool($value[$index]),
                'float' => is_float($value[$index]) || is_int($value[$index]),
            };
            if (!$valid) {
                return DecodeError::fieldTypeMismatch(
                    $class,
                    sprintf('%s[%d]', $path, $index),
                    $itemType,
                    $value[$index],
                );
            }
            if ($itemType === 'float' && is_int($value[$index])) {
                $value[$index] = (float) $value[$index];
            }
            ++$index;
        }
        return $value;
    }
}
