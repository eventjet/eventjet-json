<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Example;
use Eventjet\Json\Schema\Attribute\Format;

/**
 * A user account.
 *
 * Represents a registered user in the system.
 */
#[Example(['email' => 'john@example.com', 'createdAt' => '2024-01-01T00:00:00Z'])]
final class ClassWithAllMetadata
{
    public function __construct(
        /**
         * The user's email address.
         */
        #[Format('email')]
        #[Example('john@example.com')]
        public string $email,
        #[Format('date-time')]
        public string $createdAt,
    ) {
    }
}
