<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableMixed implements JsonSerializable
{
    #[Override]
    public function jsonSerialize(): mixed
    {
        return 'anything';
    }
}
