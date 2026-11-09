<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Override;
use Stringable;

use function var_export;

final readonly class SignedFloat implements Stringable
{
    /** Comparisons treat `-0.0` and `0.0` as equal, but their exported forms differ. */
    private string $exported;

    public function __construct(
        public float $value,
    ) {
        $this->exported = var_export($value, return: true);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->exported;
    }
}
