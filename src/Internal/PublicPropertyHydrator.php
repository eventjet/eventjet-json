<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
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
     * @throws ReflectionException
     */
    public static function hydrate(ReflectionClass $class, object $object, array $values): DecodeError|null
    {
        $constructorFields = array_fill_keys(array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            $class->getConstructor()?->getParameters() ?? [],
        ), value: true);
        $publicProperties = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $publicProperties[$property->getName()] = $property;
        }

        /** @var list<array{property: ReflectionProperty, value: mixed}> $assignments */
        $assignments = [];

        foreach ($values as $inputField => $value) {
            $property = $publicProperties[$inputField] ?? null;

            if ($property === null || $property->isStatic()) {
                continue;
            }

            if (array_key_exists($property->getName(), $constructorFields)) {
                continue;
            }

            $assignment = PublicPropertyValueConverter::convert($class->getName(), $property, $value);

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
