<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/** @psalm-suppress UndefinedClass */
final readonly class OnlyClassInUnionIsUnknown
{
    public function __construct(
        /** @phpstan-ignore-next-line class.notFound */
        public string|UnknownClass|int $val,
    ) {
    }
}
