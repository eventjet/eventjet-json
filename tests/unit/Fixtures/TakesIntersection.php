<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class TakesIntersection
{
    public function __construct(public AbstractPerson&EmployeeInterface $person)
    {
    }
}
