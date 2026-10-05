<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function enum_exists;
use function sprintf;

/** @internal */
final class ListValueConverter
{
    /**
     * @param class-string $class
     * @return list<bool|float|int|object|string>|DecodeError|null
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): array|DecodeError|null {
        $itemType = CollectionTypeResolver::resolveListItem($field);

        if ($itemType === null) {
            return null;
        }

        if (enum_exists($itemType)) {
            $enum = new ReflectionEnum($itemType);

            if (!$enum->isBacked()) {
                return DecodeError::nonBackedEnum($class, $itemType, $field->getName());
            }
        }

        $expectedType = CollectionTypeResolver::listDeclaration($field, $itemType);
        $value = ListInputNormalizer::normalize($class, $field, $expectedType, $value);

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
