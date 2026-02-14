<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\OneOf;

#[OneOf(OneOfSelfRefLeaf::class, OneOfSelfRefComposite::class)]
interface OneOfSelfRef
{
}
