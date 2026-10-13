<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;

use function array_any;
use function array_intersect;
use function array_map;
use function array_unique;
use function assert;
use function count;

/**
 * Collects the compiled members of a union, then checks that JSON values identify one member each.
 *
 * @internal
 */
final class UnionBuilder
{
    /** @var list<Node> */
    private array $members = [];
    private DecodeError|null $rejected = null;

    public function __construct(
        private readonly string $path,
    ) {}

    public function add(Node $member): void
    {
        $this->members[] = $member;
    }

    /**
     * Records a member that has no JSON representation. Its error is reported only if no other member remains.
     */
    public function reject(DecodeError $error): void
    {
        $this->rejected ??= $error;
    }

    /**
     * Returns the union of the members, or the only member if there is one.
     *
     * @throws ReflectionException
     */
    public function build(string $type): Node|DecodeError
    {
        if ($this->members === []) {
            assert($this->rejected !== null, description: 'A union with no members rejected at least one.');
            return $this->rejected;
        }
        if (count($this->members) === 1 && $this->rejected === null) {
            return $this->members[0];
        }
        $union = $this->literals() ?? $this->classes() ?? UnionNode::of($this->path, $type, $this->members);
        return $union instanceof DecodeError ? $union : $this->shapes() ?? $union;
    }

    public function classes(): DecodeError|null
    {
        $classes = [];
        foreach ($this->members as $member) {
            if ($member instanceof ObjectNode) {
                $classes[] = $member->target;
            }
            if ($member instanceof UncheckedNode) {
                $classes[] = $member->name;
            }
        }
        if (count($classes) < 2) {
            return null;
        }
        return AmbiguousUnion::classes($this->path, $classes);
    }

    /** @throws ReflectionException */
    private function literals(): DecodeError|null
    {
        $literal = array_any($this->members, static fn(Node $member): bool => $member instanceof LiteralNode);
        return $literal
            ? PhpDocLiteralUnionValidator::validate(
                $this->path,
                array_map(static fn(Node $member): string => (string) $member, $this->members),
            )
            : null;
    }

    private function shapes(): DecodeError|null
    {
        $shapes = [];
        foreach ($this->members as $member) {
            $shapes = [...$shapes, ...array_intersect($member->valueTypes(), ['array', 'stdClass'])];
        }
        if (count($shapes) === count(array_unique($shapes))) {
            return null;
        }
        return AmbiguousUnion::shapes($this->path);
    }
}
