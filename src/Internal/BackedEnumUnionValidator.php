<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_map;
use function array_shift;
use function enum_exists;
use function in_array;
use function sprintf;

/** @internal */
final class BackedEnumUnionValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, string $field, ReflectionUnionType $type): DecodeError|null
    {
        $memberNames = array_map(static fn(ReflectionType $member): string => (string) $member, $type->getTypes());
        $ambiguousPair = self::findAmbiguousPair($memberNames, $memberNames);

        if ($ambiguousPair !== null) {
            [$enum, $backingType] = $ambiguousPair;

            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $enum,
                sprintf(
                    'backed enum %s together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                    $enum,
                    $backingType,
                ),
            );
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
