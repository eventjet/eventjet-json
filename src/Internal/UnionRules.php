<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use ReflectionException;

use function count;
use function implode;
use function in_array;
use function sort;
use function sprintf;

/**
 * Rejects unions whose JSON values cannot identify a single member.
 *
 * @internal
 */
final class UnionRules
{
    /**
     * @param class-string $class
     * @param list<Node> $members
     * @throws ReflectionException
     */
    public static function check(string $class, string $path, array $members): DecodeError|null
    {
        return (
            self::classes($class, $path, $members)
            ?? EnumUnionRules::check($class, $path, $members)
            ?? self::shapes($class, $path, $members)
        );
    }

    /**
     * @param class-string $class
     * @param list<Node> $members
     */
    private static function classes(string $class, string $path, array $members): DecodeError|null
    {
        $classes = [];
        foreach ($members as $member) {
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
            $class,
            $path,
            'multiple class types:',
            implode(', ', $classes),
            '. JSON does not identify which class to instantiate.',
        );
    }

    /**
     * @param class-string $class
     * @param list<Node> $members
     */
    private static function shapes(string $class, string $path, array $members): DecodeError|null
    {
        $shapes = [];
        foreach ($members as $member) {
            $shape = match (true) {
                $member instanceof ObjectNode, $member instanceof MapNode => 'object',
                $member instanceof ListNode, $member instanceof TupleNode => 'array',
                default => null,
            };
            if ($shape !== null && in_array($shape, $shapes, strict: true)) {
                return DecodeError::nonInstantiableTarget($class, sprintf(
                    'Field %s has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                    $path,
                ));
            }
            $shapes[] = $shape;
        }
        return null;
    }
}
