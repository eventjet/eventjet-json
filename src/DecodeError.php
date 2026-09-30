<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\DecodeErrorKind;
use RuntimeException;
use Throwable;

use function get_debug_type;
use function sprintf;

final class DecodeError extends RuntimeException
{
    private function __construct(DecodeErrorKind $kind, string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, $kind->value, $previous);
    }

    public static function invalidJson(string $message): self
    {
        return new self(DecodeErrorKind::InvalidJson, sprintf('Invalid JSON: %s', $message));
    }

    public static function unexpectedRootValue(mixed $value): self
    {
        return new self(DecodeErrorKind::UnexpectedRootValue, sprintf(
            'Expected the JSON root to be an object, got %s.',
            get_debug_type($value),
        ));
    }

    /** @param class-string $class */
    public static function cannotInstantiate(string $class, Throwable $error): self
    {
        return new self(
            DecodeErrorKind::CannotInstantiate,
            sprintf('Could not create %s from the JSON object: %s', $class, $error->getMessage()),
            $error,
        );
    }
}
