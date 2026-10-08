<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class OmittedInitializedReadonlyPublicProperty
{
    public readonly string $value;

    public function __construct()
    {
        $this->value = 'readonly default';
    }
}
