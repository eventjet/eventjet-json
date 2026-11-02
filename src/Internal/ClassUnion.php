<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use ReflectionException;

use function assert;

/**
 * Replaces the classes of a union with one member that selects the class of each object by its discriminator.
 *
 * @internal
 */
final class ClassUnion
{
    /**
     * Returns the members of a union with its classes replaced. A class that is not loaded leaves
     * the classes as they are, so that the union reports them as ambiguous. If no discriminator identifies the classes,
     * the first class stands in for them, with the error, so that the union reports it where it reports conflicts
     * between classes.
     *
     * @param list<Node> $members
     * @return array{list<Node>, DecodeError|null}
     * @throws ReflectionException
     */
    public static function group(array $members, string $path, NamedDiscriminator|null $named): array
    {
        $classes = [];
        foreach ($members as $member) {
            if ($member instanceof UncheckedNode) {
                return [$members, null];
            }
            if ($member instanceof ObjectNode) {
                $classes[] = $member->target;
            }
        }
        assert($classes !== [], description: 'Only unions of several classes are grouped.');
        $unidentified = null;
        try {
            $group = DiscriminatorInference::node($classes, $path, $named);
        } catch (DecodeError $error) {
            $unidentified = $error;
            $group = null;
        }
        $grouped = [];
        $replaced = false;
        foreach ($members as $member) {
            if (!$member instanceof ObjectNode) {
                $grouped[] = $member;
                continue;
            }
            if (!$replaced) {
                $grouped[] = $group ?? $member;
                $replaced = true;
            }
        }
        return [$grouped, $unidentified];
    }
}
