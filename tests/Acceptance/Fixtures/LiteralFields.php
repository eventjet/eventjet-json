<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final readonly class LiteralFields
{
    /** @param 'foo'|42|true|false|null $value */
    public function __construct(
        public string|int|bool|null $value = 'foo',
    ) {}
}
