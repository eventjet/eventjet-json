<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/** @internal */
final class PublicProperties
{
    /** @var array<class-string, array<string, array{property: ReflectionProperty, converter: FieldValueConverter, builtinType: ReflectionNamedType|null}>> */
    private static array $properties = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, array{property: ReflectionProperty, converter: FieldValueConverter, builtinType: ReflectionNamedType|null}>|DecodeError
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

        $constructorFields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }

        $properties = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || ($constructorFields[$property->getName()] ?? false)) {
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
            $properties[$property->getName()] = [
                'property' => $property,
                'converter' => $type,
                'builtinType' => $builtinType,
            ];
        }

        return self::$properties[$name] = $properties;
    }
}
