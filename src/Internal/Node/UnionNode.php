<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\Internal\Error\InvalidValue;
use Override;

use function array_key_exists;
use function array_merge;
use function get_debug_type;

/**
 * Decodes each JSON value with the member registered for its type. Union rules ensure each type has at most one member.
 *
 * @psalm-import-type ClaimValues from Node
 * @psalm-import-type Claims from Node
 * @phpstan-import-type ClaimValues from Node
 * @phpstan-import-type Claims from Node
 * @internal
 */
final readonly class UnionNode implements Node
{
    /**
     * @param array<string, Node> $members Keyed by the get_debug_type() of the decoded JSON values they accept, or by
     *     true or false for booleans.
     * @param list<string> $dependencies The classes of all members, including members that are not indexed.
     */
    public function __construct(
        private string $type,
        private array $members,
        private array $dependencies,
    ) {}

    /**
     * Indexes union members by the JSON value types they claim. Enums that claim a type are combined, and the values
     * that literals list are decoded from a table that passes the other values of their type to the member that takes
     * them.
     *
     * @param list<array{Node, Claims}> $members Each member with its claims. They claim no JSON value differently.
     */
    public static function of(string $type, array $members): self
    {
        /** @var array<string, Node> $indexed */
        $indexed = [];
        /** @var array<string, non-empty-list<class-string<BackedEnum>>> $enums */
        $enums = [];
        /** @var array<string, ClaimValues> $listed */
        $listed = [];
        $dependencies = [];
        foreach ($members as [$member, $claims]) {
            $dependencies = [...$dependencies, ...$member->dependencies()];
            foreach ($claims as $valueType => $claim) {
                $others = $claim['others'];
                $decodes = $others === 'accepts' || $others === 'widens' && !array_key_exists($valueType, $indexed);
                if ($decodes) {
                    $indexed[$valueType] = $member;
                }
                if ($claim['enums'] !== []) {
                    $enums[$valueType] = array_merge($enums[$valueType] ?? [], $claim['enums']);
                }
                if ($others === null) {
                    $listed[$valueType] = ($listed[$valueType] ?? []) + $claim['values'];
                }
            }
        }
        foreach ($enums as $backingType => $names) {
            $indexed[$backingType] = EnumNode::union($backingType, $names);
        }
        foreach ($listed as $valueType => $values) {
            $indexed[$valueType] = LiteralNode::table($type, $valueType, $values, $indexed[$valueType] ?? null);
        }
        return new self($type, $indexed, $dependencies);
    }

    #[Override]
    public function decode(mixed $value, string $path): mixed
    {
        $valueType = match ($value) {
            true => 'true',
            false => 'false',
            default => get_debug_type($value),
        };
        $member = $this->members[$valueType] ?? null;
        return $member === null
            ? throw InvalidValue::typeMismatch($path, $this->type, $value)
            : $member->decode($value, $path);
    }

    #[Override]
    public function claims(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
