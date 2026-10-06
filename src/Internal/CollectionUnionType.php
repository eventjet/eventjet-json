<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use Stringable;

use function implode;

/** @internal */
final readonly class CollectionUnionType implements Stringable
{
    /** @param list<string> $members */
    public function __construct(
        public array $members,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return implode('|', $this->members);
    }
}
