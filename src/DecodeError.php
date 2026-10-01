<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\DecodeErrorKind;
use RuntimeException;
use Throwable;

use function get_debug_type;
use function preg_replace;
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
    public static function fieldTypeMismatch(string $class, string $field, string $expectedType, mixed $value): self
    {
        return new self(DecodeErrorKind::CannotInstantiate, sprintf(
            'Could not create %s from the JSON object: Field %s must be of type %s, %s given.',
            $class,
            $field,
            $expectedType,
            get_debug_type($value),
        ));
    }

    /** @param class-string $class */
    public static function nonBackedEnum(string $class, string $enum, string|null $field = null): self
    {
        $location = $field === null ? 'Target type' : sprintf('Field %s uses', $field);

        return new self(DecodeErrorKind::CannotInstantiate, sprintf(
            'Could not create %s from the JSON object: %s non-backed enum %s, which cannot be decoded because it has no backing value.',
            $class,
            $location,
            $enum,
        ));
    }

    /** @param class-string $class */
    public static function cannotInstantiate(string $class, Throwable $error): self
    {
        $message = preg_replace(
            pattern: '/ passed in .+ on line \d+ and /',
            replacement: ' passed and ',
            subject: $error->getMessage(),
        ) ?? $error->getMessage();

        return new self(
            DecodeErrorKind::CannotInstantiate,
            sprintf('Could not create %s from the JSON object: %s', $class, $message),
            $error,
        );
    }
}
