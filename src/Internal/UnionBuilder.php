<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;

use function array_intersect;
use function array_unique;
use function assert;
use function count;
use function implode;
use function sort;
use function sprintf;

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
            $this->classes()
            ?? EnumUnionRules::check($this->class, $this->path, $this->members)
            ?? $this->shapes()
            ?? UnionNode::of($this->class, $type, $this->members)
        );
    }

    private function classes(): DecodeError|null
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
        sort($classes);
        return DecodeError::nonInstantiableField(
            $this->class,
            $this->path,
            'multiple class types:',
            implode(', ', $classes),
            '. JSON does not identify which class to instantiate.',
        );
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
        return DecodeError::nonInstantiableTarget(
            $this->class,
            sprintf(
                'Field %s has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                $this->path,
            ),
        );
    }
}
