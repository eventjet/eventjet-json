<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Override;

use function sprintf;

/** @internal */
final readonly class NullableNode implements ItemNode
{
    public function __construct(
        private ItemNode $inner,
    ) {}

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    #[Override]
    public function decode(mixed $value, string $path): array|bool|float|int|object|string|null
    {
        return $value === null ? null : $this->inner->decode($value, $path);
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
