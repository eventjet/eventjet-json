<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk;

/** @api Consumed dynamically by NorthStarTest. */
final class KafkaRecord
{
    public string $topic = '';

    public int $partition = 0;

    public int $offset = 0;

    public int $timestamp = 0;

    public string $timestampType = '';

    public string $key = '';

    public string $value = '';

    /** @var list<array<string, list<int>>> */
    public array $headers = [];
}
