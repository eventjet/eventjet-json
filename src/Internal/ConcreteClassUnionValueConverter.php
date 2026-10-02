<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;

/** @internal */
final class ConcreteClassUnionValueConverter
{
    /** @param class-string $class */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
        stdClass $value,
    ): object|null {
        foreach ($type->getTypes() as $member) {
            /** @var ReflectionNamedType $member Supported unions contain only named types. */
            $typeName = FieldTypeNameResolver::resolve($field, $member);

            if (!enum_exists($typeName) && class_exists($typeName)) {
                return ConcreteClassValueConverter::convert($class, $field, $typeName, $value);
            }
        }

        return null;
    }
}
