<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;

enum SerializableNonBackedStatus implements JsonSerializable
{
    case Ready;

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
