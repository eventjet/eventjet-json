<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Override;

/**
 * A constructor parameter whose declared class is not loaded yet. PHP checks the value when the constructor runs.
 *
 * @internal
 */
final readonly class UncheckedNode implements Node
{
    public function __construct(
        public string $name,
    ) {}

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    #[Override]
    public function decode(mixed $value, string $path): array|bool|float|int|object|string|null
    {
        /** @var array<array-key, mixed>|bool|float|int|object|string|null */
        return $value;
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
