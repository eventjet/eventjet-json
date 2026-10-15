<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Override;
use Stringable;

/** @internal */
final readonly class PhpTypeExpression implements Stringable
{
    public function __construct(
        private string $declaration,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return $this->declaration;
    }
}
