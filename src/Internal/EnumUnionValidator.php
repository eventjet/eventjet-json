<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_filter;
use function array_map;
use function enum_exists;
use function in_array;
use function sort;
use function sprintf;
use function var_export;

/** @internal */
final class EnumUnionValidator
{
    /**
     * @param class-string $class
     * @param list<string>|null $memberNames
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        string $field,
        ReflectionUnionType $type,
        array|null $memberNames = null,
    ): DecodeError|null {
        $memberNames ??= array_map(static fn(ReflectionType $member): string => (string) $member, $type->getTypes());
        return self::validateNames($class, $field, $memberNames);
    }

    /**
     * @param class-string $class
     * @param array<array-key, string> $memberNames
     * @throws ReflectionException
     */
    public static function validateNames(string $class, string $field, array $memberNames): DecodeError|null
    {
        $enumNames = self::backedEnumNames($memberNames);

        $ambiguousPair = self::findAmbiguousPair($memberNames, $memberNames);

        if ($ambiguousPair !== null) {
            [$enum, $backingType] = $ambiguousPair;

            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $enum,
                $backingType === 'float'
                    ? ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.'
                    : sprintf(
                        ' together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                        $backingType,
                    ),
            );
        }

        $overlap = EnumBackingValueOverlap::find($enumNames);

        if ($overlap !== null) {
            [$firstEnum, $secondEnum, $value] = $overlap;

            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'multiple backed enums',
                $firstEnum . ' and ' . $secondEnum,
                sprintf(' with overlapping backing value %s. JSON cannot identify which enum case to instantiate.', var_export(
                    $value,
                    return: true,
                )),
            );
        }

        return null;
    }

    /**
     * @param array<array-key, string> $memberNames
     * @return list<enum-string>
     * @throws ReflectionException
     */
    private static function backedEnumNames(array $memberNames): array
    {
        /** @var list<enum-string> $enumNames */
        $enumNames = array_filter($memberNames, self::isBackedEnum(...));
        sort($enumNames);

        return $enumNames;
    }

    /** @throws ReflectionException */
    private static function isBackedEnum(string $type): bool
    {
        if (!enum_exists($type)) {
            return false;
        }

        return new ReflectionEnum($type)->isBacked();
    }

    /**
     * @param array<array-key, string> $remainingNames
     * @param array<array-key, string> $memberNames
     * @return array{string, string}|null
     * @throws ReflectionException
     */
    private static function findAmbiguousPair(array $remainingNames, array $memberNames): array|null
    {
        foreach ($remainingNames as $memberName) {
            $ambiguousPair = self::ambiguousPair($memberName, $memberNames);
            if ($ambiguousPair !== null) {
                return $ambiguousPair;
            }
        }
        return null;
    }

    /**
     * @param array<array-key, string> $memberNames
     * @return array{string, string}|null
     * @throws ReflectionException
     */
    private static function ambiguousPair(string $memberName, array $memberNames): array|null
    {
        if (!enum_exists($memberName)) {
            return null;
        }

        $backingType = new ReflectionEnum($memberName)->getBackingType();

        if (!$backingType instanceof ReflectionNamedType) {
            return null;
        }

        $backingTypeName = $backingType->getName();

        if ($backingTypeName === 'int' && in_array('float', $memberNames, strict: true)) {
            return [$memberName, 'float'];
        }

        if (!in_array($backingTypeName, $memberNames, strict: true)) {
            return null;
        }

        return [$memberName, $backingTypeName];
    }
}
