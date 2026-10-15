<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class SingleLiteralFields
{
    /**
     * @param 'foo' $string
     * @param 42 $integer
     * @param true $yes
     * @param false $no
     */
    public function __construct(
        public string $string = 'foo',
        public int $integer = 42,
        public bool $yes = true,
        public bool $no = false,
    ) {}
}
