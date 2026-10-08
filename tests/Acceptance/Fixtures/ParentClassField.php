<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ParentClassField extends ParentClassFieldBase
{
    public ParentClassFieldBase $value;

    public function __construct(
        public Person $person,
        parent $value,
        public string $label,
    ) {
        $this->value = $value;
    }
}
