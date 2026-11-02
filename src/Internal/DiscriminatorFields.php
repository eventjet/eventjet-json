<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Finds the members of a class that may be its discriminator.
 *
 * @internal
 */
final class DiscriminatorFields
{
    /**
     * Returns the fields of a class keyed by JSON name: its constructor parameters and public instance properties, as
     * the class decoder reads them.
     *
     * @param ReflectionClass<object> $class
     * @return array<array-key, ReflectionParameter|ReflectionProperty>
     * @throws DecodeError
     */
    public static function of(ReflectionClass $class): array
    {
        $names = TargetClass::fieldNames($class);
        $parameters = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }
        $fields = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $static = $property->isStatic();
            if (!$static) {
                $fields[$names[$name] ?? $name] = $parameters[$name] ?? $property;
            }
        }
        return $fields;
    }
}
