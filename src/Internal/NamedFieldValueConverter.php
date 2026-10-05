<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
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
    ): array|bool|float|int|object|string|null {
        $typeName = FieldTypeNameResolver::resolve($parameter, $type);

        if ($typeName === 'array') {
            $converted = ListValueConverter::convert($class, $parameter, $value);

            if ($converted !== null) {
                return $converted;
            }

            return (
                TupleValueConverter::convert($class, $parameter, $value) ?? MapValueConverter::convert(
                    $class,
                    $parameter,
                    $value,
                )
            );
        }

        if ($typeName === ArrayObject::class) {
            return ArrayObjectMapValueConverter::convert($class, $parameter, $value);
        }

        if (enum_exists($typeName)) {
            return BackedEnumValueConverter::convert($class, $parameter, $value) ?? $value;
        }

        if (class_exists($typeName)) {
            return ConcreteClassValueConverter::convert($class, $parameter, $typeName, $value);
        }

        return $value;
    }
}
