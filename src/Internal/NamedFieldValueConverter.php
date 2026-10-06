<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

use function class_exists;
use function enum_exists;

/** @internal */
final class NamedFieldValueConverter
{
    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter $parameter,
        ReflectionNamedType $type,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        $typeName = FieldTypeNameResolver::resolve($parameter, $type);

        if (enum_exists($typeName)) {
            return BackedEnumValueConverter::convert($class, $parameter, $value, $path) ?? $value;
        }

        if (class_exists($typeName)) {
            return ConcreteClassValueConverter::convert($class, $parameter, $typeName, $value, $path);
        }

        return $value;
    }
}
