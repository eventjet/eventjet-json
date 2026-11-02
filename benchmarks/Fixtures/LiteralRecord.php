<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @api Hydrated dynamically by the benchmark. */
final class LiteralRecord
{
    public const string KIND_ARTICLE = 'article';
    public const string KIND_VIDEO = 'video';

    /** @var 0.5|1.0 */
    public float $weight = 0.5;
    /** @var true */
    public bool $active = true;

    /**
     * @param 'draft'|'published'|'archived' $state
     * @param 1|2|3 $priority
     * @param self::KIND_* $kind
     * @param StringBackedStatus::Ready|StringBackedStatus::Pending $status
     * @param list<'red'|'green'|'blue'> $tags
     */
    public function __construct(
        public readonly string $state,
        public readonly int $priority,
        public readonly string $kind,
        public readonly StringBackedStatus $status,
        public readonly array $tags,
    ) {}
}
