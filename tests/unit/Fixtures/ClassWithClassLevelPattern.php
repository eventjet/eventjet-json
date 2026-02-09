<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Pattern;
use JsonSerializable;
use Override;

#[Pattern('[1-9][0-9]*')]
final readonly class ClassWithClassLevelPattern implements JsonSerializable
{
    public function __construct(private string $id)
    {
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->id;
    }
}
