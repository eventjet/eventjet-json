<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
final readonly class LabelSelectorRequirement
{
    /** @param list<string> $values */
    public function __construct(
        public string $key,
        public SelectorOperator $operator,
        public array $values,
    ) {}
}
