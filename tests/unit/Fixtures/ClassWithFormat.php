<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Format;

final class ClassWithFormat
{
    public function __construct(
        #[Format('date-time')]
        public string $createdAt,
        #[Format('email')]
        public string $email,
    ) {
    }
}
