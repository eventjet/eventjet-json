<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use RuntimeException;

final class ThrowingConstructor
{
    /** @throws RuntimeException */
    public function __construct(
        public string $value,
    ) {
        if ($value === 'rejected') {
            throw new RuntimeException('The constructor rejected the decoded value.');
        }
    }
}
