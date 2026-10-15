<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Override;

use function sprintf;

/** @internal */
final readonly class NullableNode implements Node
{
    public function __construct(
        private Node $inner,
    ) {}

    /**
     * Returns the node of a field of type `T|null`, given the compiled `T`, or null if `T|null` needs a union. Unlike a
     * list item, a field of a nullable class gets an object node that accepts null, because lists decode their objects
     * without a null check.
     */
    public static function field(Node|DecodeError|null $node): Node|null
    {
        if ($node instanceof ObjectNode) {
            return new ObjectNode($node->target, nullable: true);
        }
        return $node instanceof UncheckedNode ? $node : self::of($node);
    }

    /** A collection or enum that may be null checks for null before its own checks. */
    public static function of(Node|DecodeError|null $node): self|null
    {
        return match (true) {
            $node instanceof EnumNode,
            $node instanceof ListNode,
            $node instanceof MapNode,
            $node instanceof TupleNode,
                => new self($node),
            default => null,
        };
    }

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    #[Override]
    public function decode(mixed $value, string $path): array|bool|float|int|object|string|null
    {
        return $value === null ? null : $this->inner->decode($value, $path);
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return $this->inner->dependencies();
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s|null', $this->inner);
    }
}
