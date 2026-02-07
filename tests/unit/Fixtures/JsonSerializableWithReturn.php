<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $name,
        private readonly int $age,
    ) {
    }

    /**
     * @return array{name: string, age: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'age' => $this->age];
    }
}
