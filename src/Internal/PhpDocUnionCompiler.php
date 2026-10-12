<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
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
    /** @param class-string $class */
    public function __construct(
        private PhpDocItemCompiler $items,
        private string $class,
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
            $literal =
                $type->arguments === []
                && in_array(
                    $type->name,
                    ['null', 'true', 'false', 'resource', 'open-resource', 'closed-resource'],
                    strict: true,
                );
            $member = $literal ? $type->name : $this->items->collections()->nested($type, $path);
            $member ??= $this->items->name($type);
            if ($member === null || $member instanceof DecodeError) {
                return $member;
            }
            $member = $member instanceof Node ? $member : PhpDocClassNameResolver::canonical($member);
            $members[(string) $member] = $member;
        }
        return $members;
    }

    /**
     * @param list<PhpDocType> $types
     * @throws ReflectionException
     */
    public function compile(array $types, string $path): Node|DecodeError|null
    {
        $members = $this->members($types, $path);
        if (!is_array($members)) {
            return $members;
        }
        $union = new UnionBuilder($this->class, $path);
        foreach ($members as $member) {
            $nonEncodable = is_string($member) && NativeUnionCompiler::isNonEncodable($member);
            if ($nonEncodable) {
                $union->reject(NativeUnionCompiler::nonEncodable($this->class, $member, $path));
                continue;
            }
            $node = match (true) {
                $member instanceof Node => $member,
                in_array($member, ['null', 'true', 'false'], strict: true) => new ScalarNode(
                    $this->class,
                    $member,
                    nullable: $member === 'null',
                ),
                default => $this->items->named($member, $path),
            };
            if ($node instanceof DecodeError) {
                return $node;
            }
            $union->add($node);
        }
        return $union->build(implode('|', $members));
    }
}
