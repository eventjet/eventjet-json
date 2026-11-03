<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;

/** @internal */
final readonly class SerializedJson implements JsonSerializable
{
    public function __construct(
        private mixed $value,
    ) {}

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
