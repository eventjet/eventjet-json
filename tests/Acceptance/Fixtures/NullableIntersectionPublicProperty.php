<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Countable;
use Iterator;

final class NullableIntersectionPublicProperty
{
    public (Countable&Iterator)|null $value = null;
}
