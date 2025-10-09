<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;

use function is_float;
use function is_int;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (is_int($value) || is_float($value)) {
            return ValidationResult::valid();
        }
        return ValidationResult::error(
            sprintf('Expected number, got %s.', json_encode($value, JSON_THROW_ON_ERROR)),
            $path,
        );
    }
}
