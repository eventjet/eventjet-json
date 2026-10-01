<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionEnum;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_key_exists;
use function enum_exists;

/** @internal */
final class ObjectTypeValidator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     */
    public static function validate(ReflectionClass $class, array $values): DecodeError|null
    {
        $className = $class->getName();
        $classIsNonBackedEnum = self::isNonBackedEnum($className);

        if ($classIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($className, $className);
        }

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $intersection = self::intersection($type);

            if ($intersection !== null) {
                return DecodeError::unsupportedIntersection($className, $name, (string) $intersection);
            }

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $typeIsNonBackedEnum = self::isNonBackedEnum($typeName);

                if ($typeIsNonBackedEnum) {
                    return DecodeError::nonBackedEnum($className, $typeName, $name);
                }

                if (array_key_exists($name, $values)) {
                    /** @var mixed $value */
                    $value = $values[$name];
                    $valueMatchesType = ValueTypeMatcher::matches($value, $type);

                    if (!$valueMatchesType) {
                        $expectedType = $typeName;

                        if ($type->allowsNull() && $expectedType !== 'null') {
                            $expectedType .= '|null';
                        }

                        return DecodeError::fieldTypeMismatch($className, $name, $expectedType, $value);
                    }
                }
            }
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }

    private static function intersection(ReflectionType|null $type): ReflectionIntersectionType|null
    {
        if ($type instanceof ReflectionIntersectionType) {
            return $type;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionIntersectionType) {
                    return $member;
                }
            }
        }

        return null;
    }
}
