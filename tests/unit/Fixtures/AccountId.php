<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Attribute;
use Eventjet\Json\Format;

#[Attribute(Attribute::TARGET_PARAMETER)]
#[Format('uuid')]
final class AccountId
{
}
