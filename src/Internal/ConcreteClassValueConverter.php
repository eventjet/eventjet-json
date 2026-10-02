<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;
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
        ReflectionParameter|ReflectionProperty $field,
        string $typeName,
        mixed $value,
    ): object|null {
        $allowsNull = (bool) $field->getType()?->allowsNull();

        if ($value === null && $allowsNull) {
            return null;
        }

        if (!$value instanceof stdClass) {
            $expectedType = $typeName;

            if ($allowsNull) {
                $expectedType .= '|null';
            }

            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        return ObjectHydrator::hydrate($typeName, $value);
    }
}
