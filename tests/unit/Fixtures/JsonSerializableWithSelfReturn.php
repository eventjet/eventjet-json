<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithSelfReturn implements JsonSerializable
{
    public function __construct(
        private readonly string $name,
        private readonly self|null $child,
    ) {
    }

    /**
     * @return array{name: string, child: self|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'child' => $this->child];
    }
}
