<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

use function enum_exists;
use function in_array;

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
        $typeIsSupported = $type instanceof ReflectionNamedType && self::isSupportedType($type);

        if (!$type instanceof ReflectionNamedType || !$typeIsSupported) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'unsupported public property type',
                $type === null ? 'none' : (string) $type,
                '. Public properties outside the constructor currently support declared scalar, array, and backed enum types only.',
            );
        }

        $typeName = $type->getName();

        if (enum_exists($typeName)) {
            $enum = new ReflectionEnum($typeName);
            $enumIsBacked = $enum->isBacked();

            if (!$enumIsBacked) {
                return DecodeError::nonBackedEnum($class, $typeName, $field);
            }
        }

        return $type;
    }

    private static function isSupportedType(ReflectionNamedType $type): bool
    {
        if (enum_exists($type->getName())) {
            return true;
        }

        return $type->isBuiltin()
        && in_array(
            $type->getName(),
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
