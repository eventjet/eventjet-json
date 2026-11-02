<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedClassUnion;
use Eventjet\Json\Internal\Node\DiscriminatedNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use ReflectionClass;
use ReflectionException;

use function array_map;
use function array_values;
use function assert;
use function count;

/**
 * Replaces the classes of a union with one member that selects the class of each object by its discriminator: the
 * member that #[Discriminator] names, or the one inferred from the classes.
 *
 * @internal
 */
final class DiscriminatorInference
{
    /**
     * Returns the members of a union with its classes replaced. A class that is not loaded leaves the classes as they
     * are, so that the union reports them as ambiguous. Classes whose names differ only in case are one class.
     *
     * @param list<Node> $members
     * @param string|null $named The member that #[Discriminator] names.
     * @return list<Node>
     * @throws DecodeError If no discriminator identifies the classes.
     * @throws ReflectionException
     */
    public static function group(array $members, string $path, string|null $named): array
    {
        $classes = [];
        $others = [];
        foreach ($members as $member) {
            if ($member instanceof UncheckedNode) {
                return $members;
            }
            if (!$member instanceof ObjectNode) {
                $others[] = $member;
                continue;
            }
            $class = new ReflectionClass($member->target);
            $classes[$class->getName()] = $class;
        }
        assert($classes !== [], description: 'Only unions of several classes are grouped.');
        $others[] = self::node(array_values($classes), $path, $named);
        return $others;
    }

    /**
     * @param non-empty-list<ReflectionClass<object>> $classes
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function node(array $classes, string $path, string|null $named): Node
    {
        $names = array_map(static fn(ReflectionClass $class): string => $class->getName(), $classes);
        if (count($classes) === 1) {
            return new ObjectNode($names[0], false);
        }
        $candidates = DiscriminatorCandidates::of($classes);
        if ($named === null) {
            return self::inferred($names, $path, $candidates->all());
        }
        $tags = $candidates->tags($named);
        return $tags === null
            ? throw UnsupportedClassUnion::invalidDiscriminator($path, $named, $names)
            : DiscriminatedNode::of($names, $named, $tags);
    }

    /**
     * @param non-empty-list<class-string> $classes
     * @param list<array{string, non-empty-list<array{int|string, class-string}>}> $candidates
     * @throws DecodeError
     */
    private static function inferred(array $classes, string $path, array $candidates): DiscriminatedNode
    {
        [$member, $tags] = $candidates[0] ?? throw UnsupportedClassUnion::withoutDiscriminator($path, $classes);
        return count($candidates) === 1
            ? DiscriminatedNode::of($classes, $member, $tags)
            : throw UnsupportedClassUnion::severalDiscriminators(
                $path,
                $classes,
                array_map(
                    /** @param array{string, mixed} $candidate */
                    static fn(array $candidate): string => $candidate[0],
                    $candidates,
                ),
            );
    }
}
