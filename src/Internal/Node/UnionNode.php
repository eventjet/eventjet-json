<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Override;

use function array_key_exists;
use function array_keys;
use function get_debug_type;

/**
 * Decodes each JSON value with the member registered for its type. Union rules ensure each type has at most one member.
 *
 * @internal
 */
final readonly class UnionNode implements Node
{
    /**
     * @param class-string $class
     * @param array<string, Node> $members Keyed by the get_debug_type() of the decoded JSON values they accept, or by
     *     true or false for booleans.
     * @param Node|null $fallback Accepts values of every other type, for members that PHP checks itself.
     * @param list<string> $dependencies The classes of all members, including members that are not indexed.
     */
    public function __construct(
        private string $class,
        private string $type,
        private array $members,
        private Node|null $fallback,
        private array $dependencies,
    ) {}

    /**
     * Indexes valid union members by the JSON value types they accept, rejecting enums that JSON cannot tell apart from
     * other members. Enums that share a backing type are combined, and so are literals that share a JSON value type.
     * A `callable` member accepts every other value, because PHP checks callable arguments itself.
     *
     * @param class-string $class
     * @param list<Node> $members
     */
    public static function of(string $class, string $path, string $type, array $members): self|DecodeError
    {
        /** @var array<string, Node> $indexed */
        $indexed = [];
        $enums = [];
        $literals = [];
        $dependencies = [];
        foreach ($members as $member) {
            $dependencies = [...$dependencies, ...$member->dependencies()];
            if ($member instanceof LiteralNode) {
                $literals[] = $member;
                continue;
            }
            foreach ($member->valueTypes() as $valueType) {
                $indexed[$valueType] = $member;
            }
            if ($member instanceof EnumNode) {
                $enums[$member->backingType][] = $member->enums[0];
            }
        }
        $valueTypes = array_keys($indexed);
        foreach ($enums as $backingType => $names) {
            $node = EnumNode::union($class, $path, $backingType, $names, $valueTypes);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $indexed[$backingType] = $node;
        }
        if (array_key_exists('float', $indexed) && !array_key_exists('int', $indexed)) {
            $indexed['int'] = $indexed['float'];
        }
        $indexed = $literals === [] ? $indexed : LiteralNode::index($class, $type, $literals, $indexed);
        return new self($class, $type, $indexed, $indexed['callable'] ?? null, $dependencies);
    }

    #[Override]
    public function decode(mixed $value, string $path): mixed
    {
        $valueType = match ($value) {
            true => 'true',
            false => 'false',
            default => get_debug_type($value),
        };
        $member = $this->members[$valueType] ?? $this->fallback;
        return $member === null
            ? DecodeError::fieldTypeMismatch($this->class, $path, $this->type, $value)
            : $member->decode($value, $path);
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
