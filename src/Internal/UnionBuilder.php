<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function implode;

/**
 * Collects the compiled members of a union, then checks that JSON values identify one member each.
 *
 * @psalm-import-type Claims from Node
 * @phpstan-import-type Claims from Node
 * @internal
 */
final class UnionBuilder
{
    /** @var array<string, Node|null> Members keyed by the type that errors report, with null for rejected members. */
    private array $types = [];
    private DecodeError|null $rejected = null;

    /** How many members are classes. A union of several classes needs a discriminator. */
    private int $classes = 0;

    public function __construct(
        private readonly string $path,
        private readonly NamedDiscriminator|null $discriminator = null,
    ) {}

    /** Adds a member. A member of the same type as an earlier one replaces it, because it decodes alike. */
    public function add(Node $member): void
    {
        $this->types[(string) $member] = $member;
        if ($member instanceof ObjectNode) {
            ++$this->classes;
        }
    }

    /** Records a member whose values JSON cannot represent. Its error is reported only if no other member remains. */
    public function reject(string $type, DecodeError $error): void
    {
        $this->types[$type] = null;
        $this->rejected ??= $error;
    }

    /**
     * Returns the union of the members, or the only member if there is one. `T|null` is a nullable `T` if `T` is a
     * collection or an enum.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function build(): Node
    {
        $members = $this->members();
        [$members, $unidentified] = $this->classes > 1
            ? ClassUnion::group($members, $this->path, $this->discriminator)
            : [$members, null];
        if ($members === []) {
            assert($this->rejected !== null, description: 'A union with no members rejected at least one.');
            throw $this->rejected;
        }
        if (count($members) === 1 && $this->rejected === null && $unidentified === null) {
            return $members[0];
        }
        $nullable = $unidentified === null ? $this->nullable($members) : null;
        if ($nullable !== null) {
            return $nullable;
        }
        $claimed = array_map(
            /** @return array{Node, Claims} */
            static fn(Node $member): array => [$member, $member->claims()],
            $members,
        );
        $error = $this->conflict($claimed, $unidentified);
        if ($error !== null) {
            throw $error;
        }
        return UnionNode::of(implode('|', array_keys($this->types)), $claimed);
    }

    /** @param list<Node> $members */
    private function nullable(array $members): Node|null
    {
        if (count($members) !== 2 || $this->rejected !== null) {
            return null;
        }
        $first = $members[0];
        $second = array_pop($members);
        return match (true) {
            $first instanceof NullOnlyNode => NullableNode::of($second),
            $second instanceof NullOnlyNode => NullableNode::of($first),
            default => null,
        };
    }

    /** @return list<Node> */
    private function members(): array
    {
        return array_values(array_filter($this->types, static fn(Node|null $member): bool => $member !== null));
    }

    /**
     * Returns the error for members that JSON cannot tell apart. Only members that claim the same JSON value type can
     * conflict. Most unions have none, and don't load the rules for them.
     *
     * @param list<array{Node, Claims}> $claimed Each member with its claims.
     * @param DecodeError|null $unidentified The error for classes that no discriminator identifies.
     */
    private function conflict(array $claimed, DecodeError|null $unidentified): DecodeError|null
    {
        $types = array_merge(...array_map(
            /**
             * @param array{Node, Claims} $member
             * @return list<string>
             */
            static fn(array $member): array => array_keys($member[1]),
            $claimed,
        ));
        return count($types) === count(array_unique($types)) && $unidentified === null
            ? null
            : UnionConflicts::first($this->path, $claimed, $unidentified);
    }
}
