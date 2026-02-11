<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final readonly class WithJsonSerializableProperty
{
    public function __construct(
        public JsonSerializableNoDocblock $required,
        public JsonSerializableNoDocblock|null $nullable = null,
    ) {
    }
}
