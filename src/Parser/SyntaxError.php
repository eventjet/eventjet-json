<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

use RuntimeException;

final class SyntaxError extends RuntimeException
{
    private function __construct(string $message, public readonly Location $location)
    {
        parent::__construct($message);
    }

    public static function create(string $message, int $line, int $column): self
    {
        return new self($message, new Location($line, $column));
    }
}
