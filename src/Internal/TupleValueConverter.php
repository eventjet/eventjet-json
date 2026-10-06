<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function array_keys;
use function assert;
use function count;
use function implode;
use function is_array;
use function sprintf;

/** @internal */
final class TupleValueConverter
{
    /**
     * @param class-string $class
     * @return list<bool|float|int|object|string>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        TupleType $collection,
        mixed $value,
    ): array|DecodeError {
        $types = $collection->types;
        $required = $collection->required;
        $positions = [];

        foreach ($types as $index => $type) {
            $positions[] = $index < $required ? $type : sprintf('%d?: %s', $index, $type);
        }

        $expectedType = 'array{' . implode(', ', $positions) . '}';

        if (!is_array($value)) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        if (count($value) < $required || count($value) > count($types)) {
            $length = $required === count($types)
                ? sprintf('exactly %d', $required)
                : sprintf('between %d and %d', $required, count($types));
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Field %s must be of type %s with %s items, %d given.',
                $field->getName(),
                $expectedType,
                $length,
                count($value),
            ));
        }

        $converted = [];

        foreach (array_keys($value) as $index) {
            assert(array_key_exists($index, $value), description: 'A key returned by array_keys() must exist.');
            assert(
                array_key_exists($index, $types),
                description: 'A JSON array within the tuple length has a type for every item.',
            );
            $item = CollectionItemValueConverter::convert(
                $class,
                sprintf('%s[%d]', $field->getName(), $index),
                $types[$index],
                $value[$index],
            );

            if ($item instanceof DecodeError) {
                return $item;
            }

            $converted[] = $item;
        }

        return $converted;
    }
}
