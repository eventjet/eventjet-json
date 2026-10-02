<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;

/** @internal */
final class PublicPropertyTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionProperty $property): ReflectionNamedType|DecodeError
    {
        $field = $property->getName();
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return self::unsupportedType($class, $field, $type === null ? 'none' : (string) $type);
        }

        $typeName = FieldTypeNameResolver::resolve($property, $type);
        $typeIsSupported = self::isSupportedType($typeName);

        if (!$typeIsSupported) {
            return self::unsupportedType($class, $field, (string) $type);
        }

        if (enum_exists($typeName)) {
            $enum = new ReflectionEnum($typeName);

            if (!$enum->isBacked()) {
                return DecodeError::nonBackedEnum($class, $typeName, $field);
            }
        }

        $classTypeError = FieldTypeValidator::validateClassType($class, $field, $typeName);

        return $classTypeError ?? $type;
    }

    /** @param class-string $class */
    private static function unsupportedType(string $class, string $field, string $type): DecodeError
    {
        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'unsupported public property type',
            $type,
            '. Public properties outside the constructor currently support declared scalar, array, backed enum, and final class types only.',
        );
    }

    private static function isSupportedType(string $type): bool
    {
        if ($type !== stdClass::class && (enum_exists($type) || class_exists($type) || interface_exists($type))) {
            return true;
        }

        return in_array(
            $type,
            [
                'array',
                'bool',
                'false',
                'float',
                'int',
                'null',
                'string',
                'true',
            ],
            strict: true,
        );
    }
}
