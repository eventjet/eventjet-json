<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class MultipleEnumUnionPublicProperty
{
    public StringBackedStatus|IntBackedStatus $value = StringBackedStatus::Ready;
}
