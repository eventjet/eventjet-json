<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use stdClass;

/** @internal */
final class ConcreteClassValueConverter
{
    /**
     * @param class-string $class
     * @param class-string $typeName
     */
    public static function convert(
        string $class,
        ReflectionParameter $parameter,
        string $typeName,
        mixed $value,
    ): object|null {
        if ($value === null && $parameter->allowsNull()) {
            return null;
        }

        if (!$value instanceof stdClass) {
            $expectedType = $typeName;

            if ($parameter->allowsNull()) {
                $expectedType .= '|null';
            }

            return DecodeError::fieldTypeMismatch($class, $parameter->getName(), $expectedType, $value);
        }

        return ObjectHydrator::hydrate($typeName, $value);
    }
}
