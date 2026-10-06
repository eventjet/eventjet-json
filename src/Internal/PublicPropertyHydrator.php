<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_fill_keys;
use function array_key_exists;
use function array_map;

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
        $constructorFields = array_fill_keys(array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            $class->getConstructor()?->getParameters() ?? [],
        ), value: true);
        $publicProperties = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || array_key_exists($property->getName(), $constructorFields)) {
                continue;
            }

            $type = PublicPropertyTypeValidator::validate($class->getName(), $property);

            if ($type instanceof DecodeError) {
                return $type;
            }

            $publicProperties[$property->getName()] = ['property' => $property, ...$type];
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
