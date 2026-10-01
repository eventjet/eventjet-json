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
        if ($type instanceof ReflectionIntersectionType) {
            return DecodeError::unsupportedIntersection($class, $field, (string) $type);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::validateNamedType($class, $field, $type);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::validateUnionType($class, $field, $type);
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

        return self::validateNonInstantiableType($class, $field, $typeName);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateUnionType(string $class, string $field, ReflectionUnionType $type): DecodeError|null
    {
        $classUnionError = ClassUnionValidator::validate($class, $field, $type);

        if ($classUnionError !== null) {
            return $classUnionError;
        }

        $enumUnionError = EnumUnionValidator::validate($class, $field, $type);

        if ($enumUnionError !== null) {
            return $enumUnionError;
        }

        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionIntersectionType) {
                return DecodeError::unsupportedIntersection($class, $field, (string) $member);
            }

            $error = self::validateNamedType($class, $field, $member);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateNonInstantiableType(string $class, string $field, string $type): DecodeError|null
    {
        if (interface_exists($type)) {
            return DecodeError::nonInstantiableField($class, $field, 'interface', $type);
        }

        $typeIsAbstractClass = false;

        if (class_exists($type)) {
            $typeReflection = new ReflectionClass($type);
            $typeIsAbstractClass = $typeReflection->isAbstract();
        }

        if ($typeIsAbstractClass) {
            return DecodeError::nonInstantiableField($class, $field, 'abstract class', $type);
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
