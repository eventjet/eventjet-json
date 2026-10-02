<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use UnitEnum;

use function count;
use function enum_exists;
use function get_debug_type;
use function implode;
use function sort;
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
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
    ): UnitEnum|DecodeError|null {
        $type = $field->getType();

        if ($type instanceof ReflectionNamedType) {
            $enumName = $type->getName();

            if (!enum_exists($enumName) || $value === null && $type->allowsNull()) {
                return null;
            }

            return self::convertValue($class, $field->getName(), $enumName, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            /** @var list<enum-string> $matchingBackingEnums */
            $matchingBackingEnums = [];

            foreach ($type->getTypes() as $member) {
                /** @var ReflectionNamedType $member */
                $enumName = $member->getName();

                if (enum_exists($enumName)) {
                    $enum = new ReflectionEnum($enumName);
                    $backingType = $enum->getBackingType();
                    $valueMatchesBackingType =
                        $backingType instanceof ReflectionNamedType && ValueTypeMatcher::matches($value, $backingType);

                    if ($valueMatchesBackingType) {
                        $matchingBackingEnums[] = $enumName;
                        $case = BackedEnumCaseFinder::find($enumName, $value);

                        if ($case !== null) {
                            return $case;
                        }
                    }
                }
            }

            if ($matchingBackingEnums !== []) {
                sort($matchingBackingEnums);

                if (count($matchingBackingEnums) === 1) {
                    return self::unknownValue($class, $field->getName(), $matchingBackingEnums[0], $value);
                }

                return DecodeError::nonInstantiableField(
                    $class,
                    $field->getName(),
                    'backed enum union',
                    implode('|', $matchingBackingEnums),
                    sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
                );
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    public static function convertValue(
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
                    ', which expects a %s backing value; %s given.',
                    $backingType->getName(),
                    get_debug_type($value),
                ),
            );
        }

        $case = BackedEnumCaseFinder::find($enumName, $value);

        if ($case !== null) {
            return $case;
        }

        return self::unknownValue($class, $field, $enumName, $value);
    }

    /**
     * @param class-string $class
     * @param enum-string $enumName
     */
    private static function unknownValue(string $class, string $field, string $enumName, mixed $value): DecodeError
    {
        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'backed enum',
            $enumName,
            sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
        );
    }
}
