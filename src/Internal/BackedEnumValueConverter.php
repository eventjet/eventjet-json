<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use UnitEnum;

use function count;
use function enum_exists;
use function get_debug_type;
use function implode;
use function is_array;
use function sort;
use function sprintf;
use function var_export;

/** @internal */
final class BackedEnumValueConverter
{
    /** @var array<enum-string, string> */
    private static array $backingTypes = [];

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
        string $path,
    ): UnitEnum|DecodeError|null {
        $type = EnumFieldTypes::resolve($field);

        if (is_array($type)) {
            return self::convertUnion($class, $path, $type, $value);
        }

        return null;
    }

    /**
     * @param class-string $class
     * @param array<array-key, string> $enumNames
     * @throws ReflectionException
     */
    public static function convertUnion(
        string $class,
        string $field,
        array $enumNames,
        mixed $value,
    ): UnitEnum|DecodeError|null {
        /** @var list<enum-string> $matchingBackingEnums */
        $matchingBackingEnums = [];

        foreach ($enumNames as $enumName) {
            if (!enum_exists($enumName)) {
                continue;
            }
            $backingType = self::backingType($enumName);
            $valueMatchesBackingType = get_debug_type($value) === $backingType;

            if ($valueMatchesBackingType) {
                $matchingBackingEnums[] = $enumName;
                $case = BackedEnumCaseFinder::forEnum($enumName)->find($value);

                if ($case !== null) {
                    return $case;
                }
            }
        }

        if ($matchingBackingEnums !== []) {
            sort($matchingBackingEnums);

            if (count($matchingBackingEnums) === 1) {
                return self::unknownValue($class, $field, $matchingBackingEnums[0], $value);
            }

            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum union',
                implode('|', $matchingBackingEnums),
                sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
            );
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
        ReflectionNamedType|null $declaredType = null,
    ): UnitEnum|DecodeError {
        $backingType = self::backingType($enumName);
        if ($backingType === '') {
            return DecodeError::fieldTypeMismatch($class, $field, (string) ($declaredType ?? $enumName), $value);
        }
        $valueMatchesBackingType = get_debug_type($value) === $backingType;

        if (!$valueMatchesBackingType) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $enumName,
                sprintf(', which expects a %s backing value; %s given.', $backingType, get_debug_type($value)),
            );
        }

        $case = BackedEnumCaseFinder::forEnum($enumName)->find($value);

        if ($case !== null) {
            return $case;
        }

        return self::unknownValue($class, $field, $enumName, $value);
    }

    /**
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    private static function backingType(string $enumName): string
    {
        return self::$backingTypes[$enumName] ??= (string) new ReflectionEnum($enumName)->getBackingType();
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
