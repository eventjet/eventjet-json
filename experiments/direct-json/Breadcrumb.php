<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Prototype;

/** PROTOTYPE: build a complete error path only when an error needs it. */
final readonly class Breadcrumb
{
    public function __construct(
        public string|self $parent,
        public string $segment,
    ) {}

    public function __toString(): string
    {
        $parts = [];
        $node = $this;
        while ($node instanceof self) {
            $parts[] = $node->segment;
            $node = $node->parent;
        }
        return $node . implode('', array_reverse($parts));
    }
}
