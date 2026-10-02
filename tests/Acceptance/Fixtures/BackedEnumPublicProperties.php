<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class BackedEnumPublicProperties
{
    public StringBackedStatus $stringStatus = StringBackedStatus::Ready;
    public StringBackedStatus|null $nullableStatus = null;
    public IntBackedStatus $intStatus = IntBackedStatus::Ready;
}
