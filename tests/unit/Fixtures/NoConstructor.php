<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/** @psalm-suppress MissingConstructor */
final readonly class NoConstructor
{
    /** @phpstan-ignore-next-line property.uninitializedReadonly */
    public string $name;
}
