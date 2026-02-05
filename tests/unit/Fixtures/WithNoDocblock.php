<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * @phpstan-type Items array<mixed>
 */
final class WithNoDocblock
{
    /**
     * Intentionally has no @param docblock for testing error handling.
     * @phpstan-param Items $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
