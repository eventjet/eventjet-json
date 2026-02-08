<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * A tagged class.
 *
 * Has a description.
 *
 * @internal
 * @see https://example.com
 */
final class ClassWithDocblockAndTags
{
    public function __construct(
        public string $name,
    ) {
    }
}
