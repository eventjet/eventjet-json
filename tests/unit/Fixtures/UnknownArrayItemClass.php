<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

/**
 * @psalm-suppress UndefinedClass
 */
final class UnknownArrayItemClass
{
    /**
     * @param list<DoesNotExist> $items
     * @phpstan-ignore-next-line class.notFound
     */
    public function __construct(#[ArrayOf(DoesNotExist::class)] public array $items)
    {
    }
}
