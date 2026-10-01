<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;

final readonly class JsonSerializableTarget implements JsonSerializable
{
    public function __construct(
        public string $constructorName,
    ) {}

    /** @return array{serializedName: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['serializedName' => $this->constructorName];
    }
}
