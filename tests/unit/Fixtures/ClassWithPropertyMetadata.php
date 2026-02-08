<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Example;

final class ClassWithPropertyMetadata
{
    public function __construct(
        /**
         * The user's email address.
         *
         * Must be a valid email.
         */
        #[Example('john@example.com')]
        #[Example('jane@example.com')]
        public string $email,
    ) {
    }
}
