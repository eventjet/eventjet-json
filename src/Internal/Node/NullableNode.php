<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Override;

use function sprintf;

/** @internal */
final readonly class NullableNode implements Node
{
    public function __construct(
        private Node $inner,
    ) {}

    /**
     * Returns the node of `T|null`, given the compiled `T`, or null if `T|null` needs a union. A class accepts null
     * itself. A collection or enum that may be null checks for null before its own checks.
     */
    public static function of(Node $node): Node|null
    {
        return match (true) {
            $node instanceof ObjectNode => new ObjectNode($node->target, nullable: true),
            $node instanceof UncheckedNode => new UncheckedNode($node->name, $node->field, nullable: true),
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
    public function claims(): array
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
