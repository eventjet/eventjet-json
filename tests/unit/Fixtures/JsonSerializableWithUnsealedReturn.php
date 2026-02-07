<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithUnsealedReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    /**
     * @return array{name: string, tag: 'a'|'b', ...}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'tag' => 'a'];
    }
}
