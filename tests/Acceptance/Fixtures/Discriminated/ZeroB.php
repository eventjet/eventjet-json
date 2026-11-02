<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

final class ZeroB implements JsonSerializable
{
    use MappedJsonFields;

    /** @var 'b' */
    #[Field('0')]
    public string $kind = 'b';
}
