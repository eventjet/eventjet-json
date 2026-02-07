<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableNoDocblock implements JsonSerializable
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
