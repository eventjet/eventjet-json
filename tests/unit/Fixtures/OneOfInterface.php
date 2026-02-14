<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\OneOf;

#[OneOf(OneOfVariantA::class, OneOfVariantB::class)]
interface OneOfInterface
{
}
