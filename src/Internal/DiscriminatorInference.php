<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedClassUnion;
use Eventjet\Json\Internal\Node\DiscriminatedNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use ReflectionClass;
use ReflectionException;

use function array_intersect_key;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function in_array;

/**
 * Infers the discriminator of a union of several classes, or checks the one that #[Discriminator] names.
 *
 * @internal
 */
final class DiscriminatorInference
{
    /**
     * Returns the node that decodes the objects of the classes. Classes whose names differ only in case are one class.
     *
     * @param non-empty-list<class-string> $classes The classes of the union, in declaration order.
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function node(array $classes, string $path, NamedDiscriminator|null $named): Node
    {
        $unique = [];
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $unique[$reflection->getName()] = $reflection;
        }
        $unique = array_values($unique);
        if (count($unique) === 1) {
            return new ObjectNode($unique[0]->getName(), false);
        }
        $names = array_map(static fn(ReflectionClass $class): string => $class->getName(), $unique);
        $fields = array_map(DiscriminatorFields::of(...), $unique);
        $members = self::names(array_intersect_key(...$fields));
        $candidates = new DiscriminatorCandidates($unique, $fields);
        if ($named === null) {
            return self::inferred($names, $path, $candidates->all($members));
        }
        $named->applied = true;
        $member = $named->member;
        $tags = in_array($member, $members, strict: true) ? $candidates->tags($member) : null;
        return $tags === null
            ? throw UnsupportedClassUnion::invalidDiscriminator($path, $member, $names)
            : DiscriminatedNode::of($names, $member, $tags);
    }

    /**
     * @param non-empty-list<class-string> $classes
     * @param array<array-key, non-empty-list<array{int|string, class-string}>> $candidates
     * @throws DecodeError
     */
    private static function inferred(array $classes, string $path, array $candidates): DiscriminatedNode
    {
        $member = array_key_first($candidates);
        $tags = array_values($candidates)[0] ?? null;
        return match (true) {
            $tags === null => throw UnsupportedClassUnion::withoutDiscriminator($path, $classes),
            count($candidates) > 1 => throw UnsupportedClassUnion::severalDiscriminators(
                $path,
                $classes,
                self::names($candidates),
            ),
            default => DiscriminatedNode::of($classes, (string) $member, $tags),
        };
    }

    /**
     * Returns the keys of an array as JSON member names, which PHP turns into integers if they look like ones.
     *
     * @param array<array-key, mixed> $members
     * @return list<string>
     */
    private static function names(array $members): array
    {
        return array_map(static fn(int|string $member): string => (string) $member, array_keys($members));
    }
}
