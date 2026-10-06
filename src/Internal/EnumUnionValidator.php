<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_filter;
use function array_map;
use function array_shift;
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
     * @throws ReflectionException
     */
    public static function validate(string $class, string $field, ReflectionUnionType $type): DecodeError|null
    {
        $memberNames = array_map(static fn(ReflectionType $member): string => (string) $member, $type->getTypes());
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
                sprintf(
                    ' together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                    $backingType,
                ),
            );
        }

        $overlap = self::findOverlappingBackingValue($enumNames);

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
     * @param list<enum-string> $enumNames
     * @return array{enum-string, enum-string, int|string}|null
     * @throws ReflectionException
     */
    private static function findOverlappingBackingValue(array $enumNames): array|null
    {
        /** @var list<array{enum: enum-string, value: int|string}> $seen */
        $seen = [];

        foreach ($enumNames as $enumName) {
            $enum = new ReflectionEnum($enumName);

            foreach ($enum->getCases() as $case) {
                /** @var ReflectionEnumBackedCase $case */
                $value = $case->getBackingValue();

                foreach ($seen as $existing) {
                    if ($existing['value'] === $value) {
                        return [$existing['enum'], $enumName, $value];
                    }
                }

                $seen[] = ['enum' => $enumName, 'value' => $value];
            }
        }

        return null;
    }

    /**
     * @param array<array-key, string> $remainingNames
     * @param array<array-key, string> $memberNames
     * @return array{string, string}|null
     * @throws ReflectionException
     */
    private static function findAmbiguousPair(array $remainingNames, array $memberNames): array|null
    {
        $memberName = array_shift($remainingNames);

        if ($memberName === null) {
            return null;
        }

        $ambiguousPair = self::ambiguousPair($memberName, $memberNames);

        if ($ambiguousPair !== null) {
            return $ambiguousPair;
        }

        return self::findAmbiguousPair($remainingNames, $memberNames);
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

        if (!in_array($backingTypeName, $memberNames, strict: true)) {
            return null;
        }

        return [$memberName, $backingTypeName];
    }
}
