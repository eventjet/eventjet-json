<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

/** @api Consumed dynamically by acceptance tests. */
final readonly class ResourceIdentifier
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
