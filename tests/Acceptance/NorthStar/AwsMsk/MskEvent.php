<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final readonly class MskEvent
{
    /** @param ArrayObject<string, list<KafkaRecord>> $records */
    public function __construct(
        public string $eventSource,
        public string $eventSourceArn,
        public string $bootstrapServers,
        public ArrayObject $records,
    ) {}
}
