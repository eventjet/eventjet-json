<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Schema\Attribute\UniqueItems;
use JsonSerializable;
use Override;

/**
 * @return list<string>
 */
#[UniqueItems]
final readonly class ClassWithClassLevelUniqueItems implements JsonSerializable
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
