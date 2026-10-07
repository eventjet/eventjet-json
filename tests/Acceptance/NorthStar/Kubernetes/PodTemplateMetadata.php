<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final readonly class PodTemplateMetadata
{
    /**
     * @param ArrayObject<string, string> $labels
     * @param ArrayObject<string, string> $annotations
     */
    public function __construct(
        public ArrayObject $labels,
        public ArrayObject $annotations,
    ) {}
}
