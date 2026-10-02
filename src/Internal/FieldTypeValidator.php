<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;

/** @internal */
final class FieldTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionParameter $parameter): DecodeError|null
    {
        $field = $parameter->getName();
        $type = $parameter->getType();

        if ($type instanceof ReflectionIntersectionType) {
            return DecodeError::unsupportedIntersection($class, $field, (string) $type);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::validateNamedType($class, $parameter, $type);
        }

        if ($type instanceof ReflectionUnionType) {
            return self::validateUnionType($class, $parameter, $type);
        }

        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateNamedType(
        string $class,
        ReflectionParameter $parameter,
        ReflectionNamedType $type,
    ): DecodeError|null {
        $field = $parameter->getName();
        $typeName = ParameterTypeNameResolver::resolve($parameter, $type);

        if (in_array($typeName, ['mixed', 'object', stdClass::class], strict: true)) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'unsupported type',
                $typeName,
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            );
        }

        $typeIsNonBackedEnum = self::isNonBackedEnum($typeName);

        if ($typeIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($class, $typeName, $field);
        }

        return self::validateClassType($class, $field, $typeName);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateUnionType(
        string $class,
        ReflectionParameter $parameter,
        ReflectionUnionType $type,
    ): DecodeError|null {
        $field = $parameter->getName();
        $classUnionError = ClassUnionValidator::validate($class, $parameter, $type);

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

            $error = self::validateNamedType($class, $parameter, $member);

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
    private static function validateClassType(string $class, string $field, string $type): DecodeError|null
    {
        if (interface_exists($type)) {
            return DecodeError::nonInstantiableField($class, $field, 'interface', $type);
        }

        if (!class_exists($type)) {
            return null;
        }

        $typeReflection = new ReflectionClass($type);

        if ($typeReflection->isAbstract()) {
            return DecodeError::nonInstantiableField($class, $field, 'abstract class', $type);
        }

        if (!$typeReflection->isFinal()) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'non-final class',
                $type,
                '. Values may be subclasses, whose runtime class JSON does not identify.',
            );
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
