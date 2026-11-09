<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Override;
use RuntimeException;

use function implode;

/**
 * Why a value doesn't match a schema. Its message lists every failure, one per line.
 *
 * @api
 */
final class ValidationError extends RuntimeException
{
    /**
     * @param list<ValidationFailure> $failures
     * @internal
     */
    public function __construct(
        public readonly ValidationErrorKind $kind,
        public readonly array $failures,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param non-empty-list<ValidationFailure> $failures
     * @internal
     */
    public static function invalidValue(array $failures): self
    {
        return new self(ValidationErrorKind::InvalidValue, $failures, implode("\n", $failures));
    }

    /** @internal */
    public static function unsupportedValue(string $reason): self
    {
        return new self(ValidationErrorKind::UnsupportedValue, [], 'The value is not JSON: ' . $reason);
    }

    /** @internal */
    public static function regexLimit(string $reason): self
    {
        return new self(ValidationErrorKind::RegexLimit, [], 'Could not validate the value: ' . $reason);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
