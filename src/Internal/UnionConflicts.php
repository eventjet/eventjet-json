<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;

use function array_slice;
use function in_array;

/**
 * Finds union members that JSON cannot tell apart: members that claim the same JSON value and decode it differently.
 *
 * @internal
 */
final class UnionConflicts
{
    /**
     * Returns the error for conflicting members. If several members conflict, the kind of conflict decides which error
     * the union reports.
     *
     * @param list<array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>}> $members
     *     Each member with its claims.
     * @throws DecodeError
     */
    public static function first(string $path, array $members): DecodeError|null
    {
        $errors = [];
        foreach ($members as $index => $first) {
            foreach (array_slice($members, $index + 1) as $second) {
                $conflict = self::conflicts($path, $first, $second);
                if (!$conflict) {
                    continue;
                }
                [$kind, $error] = AmbiguousUnion::between($path, $members, $first, $second);
                $errors[$kind] ??= $error;
            }
        }
        return $errors === [] ? null : AmbiguousUnion::reported($errors);
    }

    /**
     * Whether two members claim a JSON value and decode it differently. Members that both take every value of a type
     * that they don't list conflict, unless both are scalars, or both are enums, which the union combines.
     *
     * @param array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>} $first
     * @param array{Node, array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>} $second
     * @throws DecodeError
     */
    private static function conflicts(string $path, array $first, array $second): bool
    {
        [$firstNode, $firstClaims] = $first;
        [$secondNode, $secondClaims] = $second;
        $combined =
            $firstNode::class === $secondNode::class
            && in_array($firstNode::class, [ScalarNode::class, EnumNode::class], strict: true);
        $conflicts = [];
        foreach ($firstClaims as $valueType => $claim) {
            $other = $secondClaims[$valueType] ?? ['every' => false, 'values' => []];
            $conflicts[] = $claim['every'] && $other['every'] && !$combined;
            $conflicts[] = self::differs($path, $claim['values'], $secondNode, $other);
            $conflicts[] = self::differs($path, $other['values'], $firstNode, $claim);
        }
        return in_array(true, $conflicts, strict: true);
    }

    /**
     * Whether a member decodes one of the listed JSON values to a different PHP value. A member returns PHP values for
     * the values that it lists, and a scalar also for the other values that it claims.
     *
     * @param array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}> $values
     * @param array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>} $claim
     * @throws DecodeError
     */
    private static function differs(string $path, array $values, Node $member, array $claim): bool
    {
        $scalar = $member instanceof ScalarNode && $claim['every'] ? $member : null;
        foreach ($values as $key => [$json, $value]) {
            $decoded = $scalar === null ? $claim['values'][$key][1] ?? $value : $scalar->decode($json, $path);
            if ($decoded !== $value) {
                return true;
            }
        }
        return false;
    }
}
