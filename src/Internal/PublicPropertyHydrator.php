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
     * @param T $object
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function hydrate(
        ReflectionClass $class,
        object $object,
        array $values,
        string $path,
    ): DecodeError|null {
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

            $assignment = PublicPropertyValueConverter::convert(
                $class->getName(),
                $field['property'],
                [
                    'type' => $field['type'],
                    'typeName' => $field['typeName'],
                    'collection' => $field['collection'],
                    'path' => FieldPath::field($path, $inputField),
                ],
                $value,
            );

            if ($assignment instanceof DecodeError) {
                return $assignment;
            }

            $assignments[] = $assignment;
        }

        foreach ($assignments as $assignment) {
            $assignment['property']->setValue($object, $assignment['value']);
        }

        return null;
    }
}
