<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;

use function array_filter;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function assert;
use function count;
use function implode;
use function ksort;
use function sort;
use function sprintf;
use function var_export;

/**
 * Errors for unions whose members JSON cannot tell apart.
 *
 * @internal
 */
final class AmbiguousUnion
{
    /** The kinds of conflicts between union members, in the order in which errors report them. */
    private const array ORDER = ['literals', 'classes', 'enumAndFloat', 'enumAndBackingType', 'enums', 'shapes'];

    /**
     * Returns the kind of conflict between two members of a union that claim the same JSON value and decode it
     * differently, and its error.
     *
     * @param list<array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>}> $members
     *     Each member with its claims.
     * @param array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>} $first
     * @param array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>} $second
     * @return array{string, DecodeError}
     */
    public static function between(string $field, array $members, array $first, array $second): array
    {
        [$a, $aClaims] = $first;
        [$b, $bClaims] = $second;
        $classes = self::classNames([$a, $b]);
        return match (true) {
            $a instanceof LiteralNode, $b instanceof LiteralNode => ['literals', self::overlappingLiterals($field)],
            count($classes) === 2 => [
                'classes',
                self::classes($field, $members),
            ],
            $a instanceof EnumNode && $b instanceof EnumNode => [
                'enums',
                self::overlappingEnums($field, [
                    (string) $a => $aClaims[$a->backingType]['values'] ?? [],
                    (string) $b => $bClaims[$b->backingType]['values'] ?? [],
                ]),
            ],
            $a instanceof EnumNode => self::enumAndScalar($field, $a, $b),
            $b instanceof EnumNode => self::enumAndScalar($field, $b, $a),
            default => ['shapes', self::shapes($field)],
        };
    }

    /**
     * Returns the error that a union reports, given the first error of each kind of conflict.
     *
     * @param array<string, DecodeError> $errors
     */
    public static function reported(array $errors): DecodeError|null
    {
        foreach (self::ORDER as $kind) {
            $error = $errors[$kind] ?? null;
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * @param list<Node> $members
     * @return array<int, string>
     */
    private static function classNames(array $members): array
    {
        return array_filter(array_map(static fn(Node $member): string|null => match (true) {
            $member instanceof ObjectNode => $member->target,
            $member instanceof UncheckedNode => $member->name,
            default => null,
        }, $members), static fn(string|null $class): bool => $class !== null);
    }

    /** @param list<array{Node, mixed}> $members */
    private static function classes(string $field, array $members): DecodeError
    {
        $classes = self::classNames(array_map(
            /** @param array{Node, mixed} $member */
            static fn(array $member): Node => $member[0],
            $members,
        ));
        sort($classes);
        return self::error(sprintf(
            'Field %s uses multiple class types: %s. JSON does not identify which class to instantiate.',
            $field,
            implode(', ', $classes),
        ));
    }

    private static function shapes(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
            $field,
        ));
    }

    /**
     * Names the enums in alphabetical order, with the first backing value of the second enum that the first shares.
     *
     * @param array<string, array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>> $cases
     *     The cases of each enum, keyed by its name.
     */
    private static function overlappingEnums(string $field, array $cases): DecodeError
    {
        ksort($cases);
        $names = implode(' and ', array_keys($cases));
        $second = array_pop($cases) ?? [];
        $shared = array_values(array_intersect_key($second, array_pop($cases) ?? []));
        assert($shared !== [], description: 'Conflicting enums share a backing value.');
        return self::error(sprintf(
            'Field %s uses multiple backed enums %s with overlapping backing value %s. JSON cannot identify which enum case to instantiate.',
            $field,
            $names,
            var_export($shared[0][0], return: true),
        ));
    }

    /** @return array{string, DecodeError} */
    private static function enumAndScalar(string $field, EnumNode $enum, Node $scalar): array
    {
        if ((string) $scalar === 'float') {
            return [
                'enumAndFloat',
                self::error(sprintf(
                    'Field %s uses backed enum %s together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
                    $field,
                    $enum,
                )),
            ];
        }
        return [
            'enumAndBackingType',
            self::error(sprintf(
                'Field %s uses backed enum %s together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                $field,
                $enum,
                $enum->backingType,
            )),
        ];
    }

    private static function overlappingLiterals(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s has overlapping literal union members. JSON cannot identify which PHP type to restore.',
            $field,
        ));
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
