<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final readonly class LabelSelector
{
    /**
     * @param ArrayObject<string, string> $matchLabels
     * @param list<LabelSelectorRequirement> $matchExpressions
     */
    public function __construct(
        public ArrayObject $matchLabels,
        public array $matchExpressions,
    ) {}
}
