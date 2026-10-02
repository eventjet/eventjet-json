<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ClassEnumScalarUnionPublicProperty
{
    public Person|StringBackedStatus|int|null $value = null;
}
