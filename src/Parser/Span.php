<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

final class Span
{
    /**
     * @psalm-suppress PossiblyUnusedProperty We'll use it later. Also, Psalm doesn't let use suppress this for promoted
     *     properties, that's why it isn't promoted.
     */
    public readonly Location $end;

    private function __construct(
        public readonly Location $start,
        Location $end,
    ) {
        $this->end = $end;
    }

    public static function create(int $startLine, int $startColumn, int $endLine, int $endColumn): self
    {
        return new self(new Location($startLine, $startColumn), new Location($endLine, $endColumn));
    }

    public static function char(int $line, int $column): self
    {
        return new self(new Location($line, $column), new Location($line, $column));
    }
}
