<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_fill_keys;
use function array_key_exists;
use function array_map;
use function in_array;

/** @internal */
final class PublicPropertyHydrator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param T $object
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
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

            $field = $property->getName();

            if (array_key_exists($field, $constructorFields)) {
                continue;
            }

            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType) {
                return DecodeError::nonInstantiableField(
                    $class->getName(),
                    $field,
                    'unsupported public property type',
                    $type === null ? 'none' : (string) $type,
                    '. Public properties outside the constructor currently support declared scalar types only.',
                );
            }

            $typeIsSupported = self::isSupportedType($type);

            if (!$typeIsSupported) {
                return DecodeError::nonInstantiableField(
                    $class->getName(),
                    $field,
                    'unsupported public property type',
                    (string) $type,
                    '. Public properties outside the constructor currently support declared scalar types only.',
                );
            }

            $valueMatchesType = ValueTypeMatcher::matches($value, $type);

            if (!$valueMatchesType) {
                $expectedType = $type->getName();

                if ($type->allowsNull() && $expectedType !== 'null') {
                    $expectedType .= '|null';
                }

                return DecodeError::fieldTypeMismatch($class->getName(), $field, $expectedType, $value);
            }

            $assignments[] = ['property' => $property, 'value' => $value];
        }

        foreach ($assignments as $assignment) {
            $assignment['property']->setValue($object, $assignment['value']);
        }

        return null;
    }

    private static function isSupportedType(ReflectionNamedType $type): bool
    {
        return $type->isBuiltin()
        && in_array(
            $type->getName(),
            [
                'bool',
                'false',
                'float',
                'int',
                'null',
                'string',
                'true',
            ],
            strict: true,
        );
    }
}
