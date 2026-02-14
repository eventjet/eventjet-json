<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class OneOfSelfRefComposite implements OneOfSelfRef
{
    /**
     * @param list<OneOfSelfRef> $children
     */
    public function __construct(
        public array $children,
    ) {
    }
}
