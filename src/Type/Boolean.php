<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;

use function gettype;
use function is_bool;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class Boolean extends JsonType
{
    /**
     * @internal Use {@see JsonType::boolean()} instead.
     */
    public function __construct(public readonly bool|null $value = null)
    {
    }

    /**
     * @internal Use {@see JsonType::true()} instead.
     */
    #[Override]
    public static function true(): self
    {
        return new self(true);
    }

    /**
     * @internal Use {@see JsonType::false()} instead.
     */
    #[Override]
    public static function false(): self
    {
        return new self(false);
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return 'boolean';
        }
        return $this->value ? 'true' : 'false';
    }

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if ($this->value === null) {
            if (!is_bool($value)) {
                return ValidationResult::error(sprintf('Expected boolean, got %s.', gettype($value)), $path);
            }
            return ValidationResult::valid();
        }
        if ($value === $this->value) {
            return ValidationResult::valid();
        }
        return ValidationResult::error(
            sprintf(
                'Expected %s, got %s.',
                json_encode($this->value, JSON_THROW_ON_ERROR),
                json_encode($value, JSON_THROW_ON_ERROR),
            ),
            $path,
        );
    }
}
