<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * A simple widget.
 */
final class ClassWithTitleOnly
{
    public function __construct(
        public string $id,
    ) {
    }
}
