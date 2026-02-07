<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableNoReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    /** Some docs but no return tag. */
    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
