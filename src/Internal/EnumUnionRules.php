<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;

use function array_filter;
use function array_map;
use function array_values;
use function get_debug_type;
use function in_array;
use function sort;
use function sprintf;
use function var_export;

/**
 * Rejects backed enums in unions whose JSON values cannot be told apart from another member.
 *
 * @internal
 */
final class EnumUnionRules
{
    /**
     * @param class-string $class
     * @param list<Node> $members
     * @throws ReflectionException
     */
    public static function check(string $class, string $path, array $members): DecodeError|null
    {
        $scalars = array_map(
            static fn(ScalarNode $scalar): string => $scalar->name,
            array_filter($members, static fn(Node $member): bool => $member instanceof ScalarNode),
        );
        $enums = array_values(array_filter($members, static fn(Node $member): bool => $member instanceof EnumNode));
        foreach ($enums as $enum) {
            $ambiguous = match (true) {
                $enum->backingType === 'int' && in_array('float', $scalars, strict: true)
                    => ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
                in_array($enum->backingType, $scalars, strict: true) => sprintf(
                    ' together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                    $enum->backingType,
                ),
                default => null,
            };
            if ($ambiguous !== null) {
                return DecodeError::nonInstantiableField($class, $path, 'backed enum', (string) $enum, $ambiguous);
            }
        }
        return self::overlappingCases($class, $path, $enums);
    }

    /**
     * @param class-string $class
     * @param list<EnumNode> $enums
     * @throws ReflectionException
     */
    private static function overlappingCases(string $class, string $path, array $enums): DecodeError|null
    {
        $names = array_map(static fn(EnumNode $enum): string => $enum->enum, $enums);
        sort($names);
        /** @var array<string, array<int|string, string>> $owners Keyed by backing type, because PHP casts "1" keys to 1. */
        $owners = [];
        foreach ($names as $name) {
            foreach (new ReflectionEnum($name)->getCases() as $case) {
                /** @var ReflectionEnumBackedCase $case */
                $value = $case->getBackingValue();
                $type = get_debug_type($value);
                $owner = $owners[$type][$value] ?? null;
                if ($owner !== null) {
                    return DecodeError::nonInstantiableField(
                        $class,
                        $path,
                        'multiple backed enums',
                        $owner . ' and ' . $name,
                        sprintf(' with overlapping backing value %s. JSON cannot identify which enum case to instantiate.', var_export(
                            $value,
                            return: true,
                        )),
                    );
                }
                $owners[$type][$value] = $name;
            }
        }
        return null;
    }
}
