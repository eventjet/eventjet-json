<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

/**
 * @psalm-suppress InvalidAttribute
 */
final class MultipleArrayOfAttributes
{
    /**
     * @param list<bool> $bools
     */
    public function __construct(
        #[ArrayOf('bool')]
        /** @phpstan-ignore-next-line attribute.nonRepeatable */
        #[ArrayOf('string')]
        public array $bools,
    ) {
    }
}
