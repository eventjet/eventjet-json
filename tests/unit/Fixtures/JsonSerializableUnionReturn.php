<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableUnionReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    /** @phpstan-ignore return.unusedType (Union return type needed to test schema generation for non-named return types) */
    #[Override]
    public function jsonSerialize(): string|int
    {
        return $this->value;
    }
}
