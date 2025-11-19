<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * @psalm-suppress UndefinedClass
 */
final readonly class UnknownPropertyType
{
    /**
     * @phpstan-ignore-next-line class.notFound
     */
    public function __construct(public Unknown $unknown)
    {
    }
}
