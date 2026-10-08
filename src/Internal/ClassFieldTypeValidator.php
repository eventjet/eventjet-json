<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_a;

/** @internal */
final class ClassFieldTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validateNamed(
        string $class,
        string $field,
        string $type,
        ReflectionNamedType $declaration,
    ): DecodeError|false|null {
        if ($declaration->isBuiltin()) {
            return false;
        }
        if (enum_exists($type)) {
            $enum = new ReflectionEnum($type);
            $hasSupportedValue =
                $enum->isBacked() || $declaration->allowsNull() && !$enum->implementsInterface(JsonSerializable::class);
            return $hasSupportedValue ? false : DecodeError::nonBackedEnum($class, $type, $field);
        }

        return self::validate($class, $field, $type) ?? (class_exists($type) ? false : null);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, string $field, string $type): DecodeError|null
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

        $names = FieldNames::resolve($typeReflection);
        return $names instanceof DecodeError ? $names : null;
    }

    public static function isNonEncodable(string $type): bool
    {
        if (in_array($type, ['resource', 'open-resource', 'closed-resource'], strict: true)) {
            return true;
        }

        return (
            enum_exists($type)
            && !new ReflectionEnum($type)->isBacked()
            && !is_a($type, JsonSerializable::class, allow_string: true)
        );
    }
}
