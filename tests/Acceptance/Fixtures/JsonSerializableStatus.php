<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;

enum JsonSerializableStatus: string implements JsonSerializable
{
    case Ready = 'ready';

    #[Override]
    public function jsonSerialize(): string
    {
        return 'serialized-' . $this->value;
    }
}
