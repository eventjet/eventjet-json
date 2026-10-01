<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class LabelSelector
{
    /**
     * @param array<string, string> $matchLabels
     * @param list<LabelSelectorRequirement> $matchExpressions
     */
    public function __construct(
        public array $matchLabels,
        public array $matchExpressions,
    ) {}
}
