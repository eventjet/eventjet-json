<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class MskEvent
{
    /** @param array<string, list<KafkaRecord>> $records */
    public function __construct(
        public string $eventSource,
        public string $eventSourceArn,
        public string $bootstrapServers,
        public array $records,
    ) {}
}
