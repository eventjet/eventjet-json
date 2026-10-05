<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function assert;
use function count;
use function enum_exists;
use function implode;
use function is_array;
use function sprintf;

/** @internal */
final class TupleValueConverter
{
    /**
     * @param class-string $class
     * @param list<string> $types
     * @throws ReflectionException
     */
    public static function validateTypes(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        array $types,
    ): DecodeError|null {
        foreach ($types as $index => $type) {
            $path = sprintf('%s[%d]', $field->getName(), $index);

            $isNonBackedEnum = enum_exists($type) && !new ReflectionEnum($type)->isBacked();

            if ($isNonBackedEnum) {
                return DecodeError::nonBackedEnum($class, $type, $path);
            }

            $error = ClassFieldTypeValidator::validate($class, $path, $type);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

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
        $types = CollectionTypeResolver::resolveTupleItems($field);

        if ($types === null) {
            return null;
        }

        $expectedType = 'array{' . implode(', ', $types) . '}';

        if (!is_array($value)) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        if (count($value) !== count($types)) {
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Field %s must be of type %s with exactly %d items, %d given.',
                $field->getName(),
                $expectedType,
                count($types),
                count($value),
            ));
        }

        $converted = [];

        foreach ($types as $index => $type) {
            assert(
                array_key_exists($index, $value),
                description: 'A JSON array with the tuple length contains every tuple index.',
            );
            $item = CollectionItemValueConverter::convert(
                $class,
                sprintf('%s[%d]', $field->getName(), $index),
                $type,
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
