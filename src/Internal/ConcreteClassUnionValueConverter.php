<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use ReflectionParameter;
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
        ReflectionParameter $parameter,
        ReflectionUnionType $type,
        stdClass $value,
    ): object|null {
        foreach ($type->getTypes() as $member) {
            /** @var ReflectionNamedType $member Supported unions contain only named types. */
            $typeName = FieldTypeNameResolver::resolve($parameter, $member);

            if (!enum_exists($typeName) && class_exists($typeName)) {
                return ConcreteClassValueConverter::convert($class, $parameter, $typeName, $value);
            }
        }

        return null;
    }
}
