<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function array_fill_keys;
use function array_key_exists;
use function array_map;

/**
 * @internal
 * @phpstan-type PublicFields array<string, array{property: ReflectionProperty, type: ReflectionNamedType|ReflectionUnionType, collection: ListType|MapType|TupleType|FieldCollectionUnionType|null}>
 * @psalm-type PublicFields = array<string, array{property: ReflectionProperty, type: ReflectionNamedType|ReflectionUnionType, collection: ListType|MapType|TupleType|FieldCollectionUnionType|null}>
 */
final class PublicPropertyHydrator
{
    /** @var MetadataCache<PublicFields|DecodeError>|null */
    private static MetadataCache|null $fields = null;

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
        /** @var MetadataCache<PublicFields|DecodeError> $cache */
        $cache = self::$fields ?? new MetadataCache();
        self::$fields = $cache;
        $publicProperties = $cache->resolve(
            $class->getName(),
            /**
             * @return PublicFields|DecodeError
             * @throws ReflectionException
             */
            static fn(): array|DecodeError => self::resolveFields($class),
        );

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

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return PublicFields|DecodeError
     * @throws ReflectionException
     */
    private static function resolveFields(ReflectionClass $class): array|DecodeError
    {
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

        return $publicProperties;
    }
}
