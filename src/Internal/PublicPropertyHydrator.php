<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/** @internal */
final class PublicPropertyHydrator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return list<array{property: ReflectionProperty, value: mixed}>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function prepare(ReflectionClass $class, array $values, string $path): array|DecodeError
    {
        $publicProperties = PublicProperties::resolve($class);
        if ($publicProperties instanceof DecodeError) {
            return $publicProperties;
        }

        /** @var list<array{property: ReflectionProperty, value: mixed}> $assignments */
        $assignments = [];

        foreach ($values as $inputField => $value) {
            $field = $publicProperties[$inputField] ?? null;

            if ($field === null) {
                continue;
            }

            $converted = $field['converter']->convert($class->getName(), $value, FieldPath::field($path, $inputField));
            if ($converted instanceof DecodeError) {
                return $converted;
            }
            $assignments[] = ['property' => $field['property'], 'value' => $converted];
        }

        return $assignments;
    }

    /** @param list<array{property: ReflectionProperty, value: mixed}> $assignments */
    public static function assign(object $object, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $assignment['property']->setValue($object, $assignment['value']);
        }
    }
}
