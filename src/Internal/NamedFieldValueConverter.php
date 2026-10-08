<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;

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
        ConstructorParameter $parameter,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        if ($parameter->builtin) {
            return $value;
        }

        $typeName = $parameter->typeName;

        if (enum_exists($typeName)) {
            return BackedEnumValueConverter::convert($class, $parameter->reflection, $value, $path) ?? $value;
        }

        if (class_exists($typeName)) {
            return ConcreteClassValueConverter::convert($class, $parameter->reflection, $typeName, $value, $path);
        }

        return $value;
    }
}
