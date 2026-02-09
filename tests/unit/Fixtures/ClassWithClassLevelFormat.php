<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\Format;
use JsonSerializable;
use Override;

#[Format('uuid')]
final readonly class ClassWithClassLevelFormat implements JsonSerializable
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
