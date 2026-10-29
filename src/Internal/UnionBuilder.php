<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;

use function array_any;
use function array_filter;
use function array_intersect;
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
 * @internal
 */
final class UnionBuilder
{
    /** @var array<string, Node|null> Members keyed by the type that errors report, with null for rejected members. */
    private array $types = [];
    private DecodeError|null $rejected = null;

    public function __construct(
        private readonly string $path,
    ) {}

    /** Adds a member. A member of the same type as an earlier one replaces it, because it decodes alike. */
    public function add(Node $member): void
    {
        $this->types[(string) $member] = $member;
    }

    /**
     * Records a member that failed to compile. If its values have no JSON representation, its error is reported only if
     * no other member remains. Otherwise, the error applies to the whole union.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function reject(string $type, DecodeError $error): void
    {
        $unrepresentable = NamedTypeCompiler::unrepresentable($type);
        if (!$unrepresentable) {
            throw $error;
        }
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
        if ($members === []) {
            assert($this->rejected !== null, description: 'A union with no members rejected at least one.');
            throw $this->rejected;
        }
        if (count($members) === 1 && $this->rejected === null) {
            return $members[0];
        }
        $nullable = $this->nullable($members);
        if ($nullable !== null) {
            return $nullable;
        }
        $type = implode('|', array_keys($this->types));
        $error = $this->literals($members) ?? $this->classes($members);
        if ($error !== null) {
            throw $error;
        }
        $union = UnionNode::of($this->path, $type, $members);
        $error = $this->shapes($members);
        if ($error !== null) {
            throw $error;
        }
        return $union;
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

    /** @param list<Node> $members */
    private function classes(array $members): DecodeError|null
    {
        $classes = array_values(array_filter(array_map(static fn(Node $member): string|null => match (true) {
            $member instanceof ObjectNode => $member->target,
            $member instanceof UncheckedNode => $member->name,
            default => null,
        }, $members), static fn(string|null $class): bool => $class !== null));
        if (count($classes) < 2) {
            return null;
        }
        return AmbiguousUnion::classes($this->path, $classes);
    }

    /**
     * @param list<Node> $members
     * @throws ReflectionException
     */
    private function literals(array $members): DecodeError|null
    {
        $literal = array_any($members, static fn(Node $member): bool => $member instanceof LiteralNode);
        return $literal ? PhpDocLiteralUnionValidator::validate($this->path, $members) : null;
    }

    /** @param list<Node> $members */
    private function shapes(array $members): DecodeError|null
    {
        $shapes = array_merge(...array_map(
            /** @return array<int, string> */
            static fn(Node $member): array => array_intersect($member->valueTypes(), ['array', 'stdClass']),
            $members,
        ));
        if (count($shapes) === count(array_unique($shapes))) {
            return null;
        }
        return AmbiguousUnion::shapes($this->path);
    }
}
