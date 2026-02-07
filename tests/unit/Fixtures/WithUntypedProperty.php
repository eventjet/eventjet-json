<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class WithUntypedProperty
{
    /**
     * @psalm-suppress MissingPropertyType - Intentionally untyped to test fallback to Schema::mixed()
     * @phpstan-ignore missingType.property (Intentionally untyped to test fallback to Schema::mixed())
     */
    public $value = 'default';

    public function __construct(
        public string $name,
    ) {
    }
}
