<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\Internal\Error\InvalidValue;
use Override;

use function array_key_exists;
use function get_debug_type;

/**
 * Decodes each JSON value with the member registered for its type. Union rules ensure each type has at most one member.
 *
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
     * Indexes union members by the JSON value types they claim. Enums that share a backing type are combined, and
     * literals pass the other values of their JSON value type to the member that takes every value of it.
     *
     * @param list<Node> $members Members that claim no JSON value differently.
     */
    public static function of(string $type, array $members): self
    {
        /** @var array<string, Node> $indexed */
        $indexed = [];
        $enums = [];
        $literals = [];
        $dependencies = [];
        foreach ($members as $member) {
            $dependencies = [...$dependencies, ...$member->dependencies()];
            if ($member instanceof LiteralNode) {
                $literals[] = $member;
                continue;
            }
            if ($member instanceof EnumNode) {
                $enums[$member->backingType][] = $member->enums[0];
                continue;
            }
            // An unknown class claims JSON objects only to conflict with other classes, so values report the union type.
            if ($member instanceof UncheckedNode) {
                continue;
            }
            foreach ($member->claims() as $valueType => $claim) {
                // Scalars that claim a type decode it alike, except that `int` takes integers from `float`.
                $taken = array_key_exists($valueType, $indexed) && (string) $member !== $valueType;
                if (!$claim['every'] || $taken) {
                    continue;
                }
                $indexed[$valueType] = $member;
            }
        }
        foreach ($enums as $backingType => $names) {
            $indexed[$backingType] = EnumNode::union($backingType, $names);
        }
        $indexed = $literals === [] ? $indexed : LiteralNode::index($type, $literals, $indexed);
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
