<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

use function class_exists;
use function enum_exists;
use function is_array;

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

        if ($typeName === 'array' && ($value instanceof stdClass || is_array($value))) {
            $converted = ScalarListValueConverter::convert($class, $parameter, $value);

            return $converted ?? ObjectValueConverter::convertArrayValue($value);
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
