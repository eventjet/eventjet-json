<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Discriminator;

final class EventLog
{
    /** @var list<Created|Deleted> */
    #[Discriminator('schema')]
    public array $archived = [];

    /** @var list<list<Created|Deleted>> */
    #[Discriminator('schema')]
    public array $batches = [];

    /** @var array{Created|Deleted, list<Created|Deleted>}|null */
    #[Discriminator('schema')]
    public array|null $pair = null;

    /** @var list<Created|Deleted> */
    public array $events;

    /** @param list<Created|Deleted> $events */
    public function __construct(
        #[Discriminator('@type')]
        public Created|Deleted $latest,
        #[Discriminator('@type')]
        array $events,
    ) {
        $this->events = $events;
    }
}
