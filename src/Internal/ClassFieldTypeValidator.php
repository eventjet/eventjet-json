<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

use function class_exists;
use function enum_exists;
use function interface_exists;

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
        if (enum_exists($type)) {
            $enum = new ReflectionEnum($type);
            $hasSupportedValue = $declaration->allowsNull() && !$enum->implementsInterface(JsonSerializable::class);
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

        $names = RootTypeValidator::fieldNames($typeReflection);
        return $names instanceof DecodeError ? $names : null;
    }

    public static function classUnionMember(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
    ): string|null {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = FieldTypeNameResolver::resolve($field, $type);

        if (enum_exists($name) || interface_exists($name)) {
            return null;
        }

        return $name;
    }
}
