<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

use function array_filter;
use function array_push;
use function array_values;

/** @internal */
final class PublicProperties
{
    /** @var array<class-string, array<array-key, array{property: ReflectionProperty, converter: FieldValueConverter, builtinType: ReflectionNamedType|null}>> */
    private static array $properties = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<array-key, array{property: ReflectionProperty, converter: FieldValueConverter, builtinType: ReflectionNamedType|null}>|DecodeError
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function resolve(ReflectionClass $class): array|DecodeError
    {
        $name = $class->getName();
        $cached = self::$properties[$name] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $names = RootTypeValidator::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }
        $constructorFields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }

        $properties = [];
        foreach (RootTypeValidator::declarations($class) as $property) {
            $publicInstance =
                ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
                === ReflectionProperty::IS_PUBLIC;
            if (!$publicInstance || ($constructorFields[$property->getName()] ?? false)) {
                continue;
            }
            $type = PublicPropertyTypeValidator::validate($name, $property);
            if ($type instanceof DecodeError) {
                return $type;
            }
            $declaredType = $property->getType();
            $builtinType =
                $declaredType instanceof ReflectionNamedType
                && $declaredType->isBuiltin()
                && $declaredType->getName() !== 'array'
                    ? $declaredType
                    : null;
            $properties[$names[$property->getName()] ?? $property->getName()] = [
                'property' => $property,
                'converter' => $type,
                'builtinType' => $builtinType,
            ];
        }

        return self::$properties[$name] = $properties;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return list<ReflectionProperty>
     */
    public static function mappedFields(ReflectionClass $class): array
    {
        $properties = RootTypeValidator::declarations($class);
        // Reflection omits private ancestor properties from the effective child declarations.
        $parent = $class->getParentClass();
        while ($parent !== false) {
            array_push($properties, ...$parent->getProperties(ReflectionProperty::IS_PRIVATE));
            $parent = $parent->getParentClass();
        }
        return array_values(array_filter(
            $properties,
            static fn(ReflectionProperty $property): bool => $property->getAttributes(Field::class) !== [],
        ));
    }
}
