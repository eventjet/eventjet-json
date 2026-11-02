<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;

/** @internal */
abstract class MappedPropertyBase
{
    #[Field('$anchor')]
    public string $anchor = 'original';
}
