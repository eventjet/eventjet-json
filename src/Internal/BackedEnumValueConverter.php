<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use UnitEnum;

use function enum_exists;
use function get_debug_type;
use function sprintf;
use function var_export;

/** @internal */
final class BackedEnumValueConverter
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter $parameter,
        mixed $value,
    ): UnitEnum|DecodeError|null {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            $enumName = $type->getName();

            if (!enum_exists($enumName) || $value === null && $type->allowsNull()) {
                return null;
            }

            return self::convertValue($class, $parameter->getName(), $enumName, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                /** @var ReflectionNamedType $member */
                $enumName = $member->getName();

                if (enum_exists($enumName)) {
                    $enum = new ReflectionEnum($enumName);
                    /** @var ReflectionNamedType $backingType */
                    $backingType = $enum->getBackingType();
                    $valueMatchesBackingType = ValueTypeMatcher::matches($value, $backingType);

                    if ($valueMatchesBackingType) {
                        return self::convertValue($class, $parameter->getName(), $enumName, $value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    private static function convertValue(
        string $class,
        string $field,
        string $enumName,
        mixed $value,
    ): UnitEnum|DecodeError {
        $enum = new ReflectionEnum($enumName);
        /** @var ReflectionNamedType $backingType */
        $backingType = $enum->getBackingType();
        $valueMatchesBackingType = ValueTypeMatcher::matches($value, $backingType);

        if (!$valueMatchesBackingType) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $enumName,
                sprintf(
                    'backed enum %s, which expects a %s backing value; %s given.',
                    $enumName,
                    $backingType->getName(),
                    get_debug_type($value),
                ),
            );
        }

        foreach ($enum->getCases() as $case) {
            if ($case instanceof ReflectionEnumBackedCase && $case->getBackingValue() === $value) {
                return $case->getValue();
            }
        }

        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'backed enum',
            $enumName,
            sprintf(
                'backed enum %s, which has no case with backing value %s.',
                $enumName,
                var_export($value, return: true),
            ),
        );
    }
}
