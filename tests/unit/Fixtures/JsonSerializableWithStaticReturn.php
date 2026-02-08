<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithStaticReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    /**
     * @return array{value: string, items: list<static>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'items' => []];
    }
}
