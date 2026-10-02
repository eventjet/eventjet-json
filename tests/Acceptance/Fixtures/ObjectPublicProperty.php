<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use stdClass;

final class ObjectPublicProperty
{
    public object $value;

    public function __construct()
    {
        $this->value = new stdClass();
    }
}
