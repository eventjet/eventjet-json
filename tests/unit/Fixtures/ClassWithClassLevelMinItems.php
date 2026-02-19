<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\MinItems;
use JsonSerializable;
use Override;

#[MinItems(1)]
final readonly class ClassWithClassLevelMinItems implements JsonSerializable
{
    /**
     * @param list<string> $items
     */
    public function __construct(private array $items)
    {
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
