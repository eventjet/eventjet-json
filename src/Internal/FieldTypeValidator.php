<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function enum_exists;
use function in_array;

/** @internal */
final class FieldTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError|null
    {
        $fieldName = $field->getName();
        $type = $field->getType();

        if ($type instanceof ReflectionIntersectionType) {
            return DecodeError::unsupportedIntersection($class, $fieldName, (string) $type);
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
    private static function validateNamedType(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType $type,
    ): DecodeError|null {
        $fieldName = $field->getName();
        $typeName = FieldTypeNameResolver::resolve($field, $type);

        if (in_array($typeName, ['mixed', 'object', stdClass::class], strict: true)) {
            return DecodeError::nonInstantiableField(
                $class,
                $fieldName,
                'unsupported type',
                $typeName,
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            );
        }

        $typeIsNonBackedEnum = self::isNonBackedEnum($typeName);

        if ($typeIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($class, $typeName, $fieldName);
        }

        if ($typeName === 'array') {
            $listItemTypeError = ListItemTypeValidator::validate($class, $field);

            if ($listItemTypeError !== null) {
                return $listItemTypeError;
            }
        }

        return ClassFieldTypeValidator::validate($class, $fieldName, $typeName);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateUnionType(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): DecodeError|null {
        $fieldName = $field->getName();
        $classUnionError = ClassUnionValidator::validate($class, $field, $type);

        if ($classUnionError !== null) {
            return $classUnionError;
        }

        $enumUnionError = EnumUnionValidator::validate($class, $fieldName, $type);

        if ($enumUnionError !== null) {
            return $enumUnionError;
        }

        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionIntersectionType) {
                return DecodeError::unsupportedIntersection($class, $fieldName, (string) $member);
            }

            $error = self::validateNamedType($class, $field, $member);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
