<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\EnumUnionNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UnionNode;

use function array_key_exists;

/**
 * Indexes valid union members by the JSON value types they accept: their get_debug_type(), or true or false.
 * A `callable` member accepts every value, because PHP checks callable arguments itself.
 *
 * @internal
 */
final class UnionIndex
{
    /**
     * @param class-string $class
     * @param list<Node> $members
     */
    public static function node(string $class, string $type, array $members): UnionNode
    {
        /** @var array<string, Node> $indexed */
        $indexed = [];
        $enums = [];
        foreach ($members as $member) {
            foreach (self::valueTypes($member) as $valueType) {
                $indexed[$valueType] = $member;
            }
            if ($member instanceof EnumNode) {
                $enums[$member->backingType][] = $member->enum;
            }
        }
        foreach ($enums as $backingType => $names) {
            $indexed[$backingType] = new EnumUnionNode($class, $names);
        }
        if (array_key_exists('float', $indexed) && !array_key_exists('int', $indexed)) {
            $indexed['int'] = $indexed['float'];
        }
        return new UnionNode($class, $type, $indexed, $indexed['callable'] ?? null);
    }

    /** @return list<string> */
    private static function valueTypes(Node $member): array
    {
        return match (true) {
            $member instanceof ObjectNode, $member instanceof MapNode => ['stdClass'],
            $member instanceof ListNode, $member instanceof TupleNode => ['array'],
            $member instanceof ScalarNode && $member->name === 'bool' => ['true', 'false'],
            $member instanceof ScalarNode => [$member->name],
            default => [],
        };
    }
}
