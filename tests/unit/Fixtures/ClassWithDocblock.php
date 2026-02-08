<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * A person.
 *
 * Represents a person with a name and age.
 */
final class ClassWithDocblock
{
    public function __construct(
        public string $name,
        public int $age,
    ) {
    }
}
