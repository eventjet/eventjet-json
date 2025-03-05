<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

final class TokenLocation
{
    public function __construct(
        public readonly Token|string|int|float|bool|null $token,
        public readonly Span $location,
    ) {
    }
}
