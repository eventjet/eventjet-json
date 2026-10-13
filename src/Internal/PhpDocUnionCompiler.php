<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;

use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * Compiles PHPDoc unions of collection items, such as `int|string` in `list<int|string>`.
 *
 * @internal
 */
final readonly class PhpDocUnionCompiler
{
    public function __construct(
        private PhpDocItemCompiler $items,
    ) {}

    /**
     * Resolves the members of a PHPDoc union, keyed by their type names: collections as nodes, other types as names.
     * Returns null if a member is not recognized.
     *
     * @param list<PhpDocType> $types
     * @return array<string, Node|string>|DecodeError|null
     * @throws ReflectionException
     */
    public function members(array $types, string $path): array|DecodeError|null
    {
        $members = [];
        foreach ($types as $type) {
            $resolved = $this->resolve($type, $path);
            if (!is_array($resolved)) {
                return $resolved;
            }
            foreach ($resolved as $member) {
                $members[(string) $member] = $member;
            }
        }
        return $members;
    }

    /**
     * Resolves one member: a collection as a node; a name, or the literals that a constant stands for, as names.
     *
     * @return non-empty-list<Node|string>|DecodeError|null
     * @throws ReflectionException
     */
    private function resolve(PhpDocType $type, string $path): array|DecodeError|null
    {
        $keyword =
            $type->arguments === []
            && in_array(
                $type->name,
                ['null', 'true', 'false', 'resource', 'open-resource', 'closed-resource'],
                strict: true,
            );
        $member = $keyword ? $type->name : $this->items->collections()->nested($type, $path);
        $member ??= $this->items->name($type);
        return match (true) {
            $member === null => $this->items->literals($type),
            $member instanceof DecodeError => $member,
            $member instanceof Node => [$member],
            default => [PhpDocClassNameResolver::canonical($member)],
        };
    }

    /**
     * @param list<PhpDocType> $types
     * @throws ReflectionException
     */
    public function compile(array $types, string $path): Node|DecodeError|null
    {
        $members = $this->members($types, $path);
        return is_array($members) ? $this->build($members, $path) : $members;
    }

    /**
     * Compiles the union of resolved members, or the only member if there is one.
     *
     * @param array<string, Node|string> $members
     * @throws ReflectionException
     */
    public function build(array $members, string $path): Node|DecodeError
    {
        $union = new UnionBuilder($path);
        foreach ($members as $member) {
            $nonEncodable = is_string($member) && NativeUnionCompiler::isNonEncodable($member);
            if ($nonEncodable) {
                $union->reject(NativeUnionCompiler::nonEncodable($member, $path));
                continue;
            }
            $node = $member instanceof Node ? $member : $this->items->member($member, $path);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $union->add($node);
        }
        return $union->build(implode('|', $members));
    }
}
