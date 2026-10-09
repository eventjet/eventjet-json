<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

/** @api Declaration fixture for direct-parser eligibility. */
abstract class AbstractDirectRecords
{
    /** @param list<DirectRecord> $records */
    public function __construct(
        public array $records,
    ) {}
}
