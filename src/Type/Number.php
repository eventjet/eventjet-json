<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use function is_float;
use function is_int;
use function json_encode;
use function sprintf;

final class Number extends JsonType
{
    /**
     * @internal Use {@see JsonType::number()} instead.
     */
    public function __construct()
    {
    }

    public function __toString(): string
    {
        return 'number';
    }

    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (is_int($value) || is_float($value)) {
            return ValidationResult::valid();
        }
        return ValidationResult::error(sprintf('Expected number, got %s.', (string)json_encode($value)), $path);
    }
}
