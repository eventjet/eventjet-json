<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

final class AndFilter
{
    /**
     * @param 'and' $type
     * @param list<AndFilter|OrFilter|Equals> $operands
     */
    public function __construct(
        public string $type,
        public array $operands,
    ) {}
}
