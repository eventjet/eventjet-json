<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

final class ZeroA implements JsonSerializable
{
    use MappedJsonFields;

    /** @var 'a' */
    #[Field('0')]
    public string $kind = 'a';
}
