<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use function json_encode;
use function sprintf;

final class Null_ extends JsonType
{
    /**
     * @internal Use {@see JsonType::null()} instead.
     */
    public function __construct()
    {
    }

    public function __toString(): string
    {
        return 'null';
    }

    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::valid();
        }
        return ValidationResult::error(sprintf('Expected null, got %s.', (string)json_encode($value)), $path);
    }
}
