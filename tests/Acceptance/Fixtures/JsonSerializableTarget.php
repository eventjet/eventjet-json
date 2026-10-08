<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableTarget implements JsonSerializable
{
    public string $constructorName = '';

    /** @return array{serializedName: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['serializedName' => $this->constructorName];
    }
}
