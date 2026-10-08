<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Prototype;

final readonly class DeepNode
{
    public function __construct(
        public int $id,
        public self|null $child = null,
    ) {}
}
