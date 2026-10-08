<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class DisjointStringBackedEnumUnionPublicProperty
{
    public StringBackedStatus|StringBackedOutcome $value = StringBackedStatus::Ready;
}
