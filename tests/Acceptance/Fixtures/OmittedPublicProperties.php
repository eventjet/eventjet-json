<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class OmittedPublicProperties
{
    public string $initialized = 'default';
    public int $uninitialized = 0;

    public function __construct()
    {
        unset($this->uninitialized);
    }
}
