<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Node\Node;

use function array_slice;
use function in_array;

/**
 * Finds union members that JSON cannot tell apart: members that claim the same JSON value and decode it differently.
 *
 * @psalm-import-type Claim from Node
 * @psalm-import-type ClaimValues from Node
 * @psalm-import-type Claims from Node
 * @phpstan-import-type Claim from Node
 * @phpstan-import-type ClaimValues from Node
 * @phpstan-import-type Claims from Node
 * @internal
 */
final class UnionConflicts
{
    /**
     * Returns the error for conflicting members. If several members conflict, the kind of conflict decides which error
     * the union reports.
     *
     * @param list<array{Node, Claims}> $members Each member with its claims.
     */
    public static function first(string $path, array $members): DecodeError|null
    {
        $errors = [];
        foreach ($members as $index => $first) {
            foreach (array_slice($members, $index + 1) as $second) {
                $conflict = self::conflict($first, $second);
                if ($conflict === null) {
                    continue;
                }
                [$kind, $error] = AmbiguousUnion::between($path, $members, ...$conflict);
                $errors[$kind] ??= $error;
            }
        }
        return $errors === [] ? null : AmbiguousUnion::reported($errors);
    }

    /**
     * Returns the first JSON value type for which two members claim a value and return different PHP values for it,
     * with each member and its claim for that type.
     *
     * @param array{Node, Claims} $first
     * @param array{Node, Claims} $second
     * @return array{string, array{Node, Claim}, array{Node, Claim}}|null
     */
    private static function conflict(array $first, array $second): array|null
    {
        foreach ($first[1] as $valueType => $claim) {
            $other = $second[1][$valueType] ?? null;
            if ($other === null) {
                continue;
            }
            $conflicts = [
                self::compete($claim, $other),
                self::differs($claim['values'], $other),
                self::differs($other['values'], $claim),
            ];
            if (in_array(true, $conflicts, strict: true)) {
                return [$valueType, [$first[0], $claim], [$second[0], $other]];
            }
        }
        return null;
    }

    /**
     * Whether both members take the values of a type that they don't list. A member that widens them leaves them to
     * one that accepts them, and the union combines enums into one node.
     *
     * @param Claim $first
     * @param Claim $second
     */
    private static function compete(array $first, array $second): bool
    {
        $others = [$first['others'], $second['others']];
        $yields = in_array($others, [['widens', 'accepts'], ['accepts', 'widens']], strict: true);
        return (
            !in_array(null, $others, strict: true)
            && !$yields
            && ($first['enums'] === [] || $second['enums'] === [])
        );
    }

    /**
     * Whether a member returns a different PHP value for one of the listed JSON values. A member returns PHP values
     * for the values that it lists, and for the other values of the type if it accepts or widens them. Listed values
     * are scalars, which a member that accepts them returns as they are.
     *
     * @param ClaimValues $values
     * @param Claim $claim
     */
    private static function differs(array $values, array $claim): bool
    {
        foreach ($values as $key => [$json, $value]) {
            $returned = $claim['values'][$key][1] ?? match ($claim['others']) {
                'accepts' => $json,
                'widens' => (float) $json,
                default => $value,
            };
            if ($returned !== $value) {
                return true;
            }
        }
        return false;
    }
}
