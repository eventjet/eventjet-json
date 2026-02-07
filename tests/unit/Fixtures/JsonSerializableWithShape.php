<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithShape implements JsonSerializable
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    /**
     * @return array{name: string, tags: string[]}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'tags' => []];
    }
}
