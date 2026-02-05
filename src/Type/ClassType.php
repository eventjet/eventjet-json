<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

final readonly class ClassType implements ParsedType
{
    public function __construct(
        public string $className,
    ) {
    }
}
