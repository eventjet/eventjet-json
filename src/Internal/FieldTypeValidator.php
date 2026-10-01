<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function class_exists;
use function enum_exists;
use function interface_exists;

/** @internal */
final class FieldTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, string $field, ReflectionType|null $type): DecodeError|null
    {
        $intersection = self::intersection($type);

        if ($intersection !== null) {
            return DecodeError::unsupportedIntersection($class, $field, (string) $intersection);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::validateNamedType($class, $field, $type);
        }

        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateNamedType(string $class, string $field, ReflectionNamedType $type): DecodeError|null
    {
        $typeName = $type->getName();
        $typeIsNonBackedEnum = self::isNonBackedEnum($typeName);

        if ($typeIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($class, $typeName, $field);
        }

        if (interface_exists($typeName)) {
            return DecodeError::nonInstantiableField($class, $field, 'interface', $typeName);
        }

        $typeIsAbstractClass = false;

        if (class_exists($typeName)) {
            $typeReflection = new ReflectionClass($typeName);
            $typeIsAbstractClass = $typeReflection->isAbstract();
        }

        if ($typeIsAbstractClass) {
            return DecodeError::nonInstantiableField($class, $field, 'abstract class', $typeName);
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
