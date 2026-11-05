<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use Eventjet\Json\Internal\Node\Node;
use Override;

/** A node that returns every value unchanged and counts its calls. */
final class CountingNode implements Node
{
    public int $calls = 0;

    #[Override]
    public function decode(mixed $value): mixed
    {
        ++$this->calls;
        /** @var array<array-key, mixed>|bool|float|int|object|string|null */
        return $value;
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function claims(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return 'counted';
    }
}
