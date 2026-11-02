<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

/** @api Consumed dynamically by acceptance tests. */
final readonly class Document
{
    /**
     * @param Resource|list<Resource>|null $data
     * @param list<Resource> $included
     */
    public function __construct(
        public Resource|array|null $data,
        public array $included,
    ) {}
}
