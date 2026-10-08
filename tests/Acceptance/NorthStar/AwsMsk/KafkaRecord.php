<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk;

use ArrayObject;

/** @api Consumed dynamically by acceptance tests. */
final class KafkaRecord
{
    public string $topic = '';

    public int $partition = 0;

    public int $offset = 0;

    public int $timestamp = 0;

    public string $timestampType = '';

    public string $key = '';

    public string $value = '';

    /** @var list<ArrayObject<string, list<int>>> */
    public array $headers = [];
}
