<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::valid();
        }
        return ValidationResult::error(
            sprintf('Expected null, got %s.', json_encode($value, JSON_THROW_ON_ERROR)),
            $path,
        );
    }
}
