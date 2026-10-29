<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class BackedEnumFields
{
    public function __construct(
        public StringBackedStatus $stringStatus,
        public StringBackedStatus|null $nullableStatus,
        public IntBackedStatus $intStatus,
    ) {}
}
