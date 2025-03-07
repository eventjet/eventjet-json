<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use function is_string;
use function json_encode;
use function sprintf;

final class String_ extends JsonType
{
    /**
     * @internal Use {@see JsonType::string()} instead.
     */
    public function __construct()
    {
    }

    public function __toString(): string
    {
        return 'string';
    }

    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::error(sprintf('Expected string, got %s.', (string)json_encode($value)), $path);
        }
        return ValidationResult::valid();
    }
}
