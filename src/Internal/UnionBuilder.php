<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;

use function assert;

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

    /** @param class-string $class */
    public function __construct(
        private readonly string $class,
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

    /** @throws ReflectionException */
    public function build(string $type): UnionNode|DecodeError
    {
        if ($this->members === []) {
            assert($this->rejected !== null, description: 'A union with no members rejected at least one.');
            return $this->rejected;
        }
        return (
            UnionRules::check($this->class, $this->path, $this->members) ?? UnionIndex::node(
                $this->class,
                $type,
                $this->members,
            )
        );
    }
}
