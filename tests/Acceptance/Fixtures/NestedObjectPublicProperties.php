<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class NestedObjectPublicProperties
{
    public Person $person;
    public Person|null $alternate = null;
    public self|null $child = null;

    public function __construct()
    {
        $this->person = new Person('', '');
    }
}
