<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class Label
{
    /** @param 'label' $kind */
    public function __construct(
        public string $kind,
        public string $text,
    ) {}
}
