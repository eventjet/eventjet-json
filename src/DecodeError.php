<?php

declare(strict_types=1);

namespace Eventjet\Json;

use RuntimeException;
use Throwable;

use function get_debug_type;
use function preg_replace;
use function sprintf;

/** @api */
final class DecodeError extends RuntimeException
{
    private bool $named = false;

    /** @internal */
    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $kind->value, $previous);
    }

    public static function invalidJson(string $message): self
    {
        return new self(DecodeErrorKind::InvalidJson, sprintf('Invalid JSON: %s', $message));
    }

    /** @param 'array'|'object' $expectedType */
    public static function unexpectedRootValue(mixed $value, string $expectedType = 'object'): self
    {
        return new self(DecodeErrorKind::UnexpectedRootValue, sprintf(
            'Expected the JSON root to be an %s, got %s.',
            $expectedType,
            get_debug_type($value),
        ));
    }

    /**
     * Reports an error thrown by other code, such as a constructor, with its message and as the previous exception.
     *
     * @internal
     */
    public static function thrown(DecodeErrorKind $kind, Throwable $error): self
    {
        $message = preg_replace(
            pattern: '/ passed in .+ on line \d+ and /',
            replacement: ' passed and ',
            subject: $error->getMessage(),
        ) ?? $error->getMessage();

        return new self($kind, $message, $error);
    }

    /**
     * Names the class whose object could not be created. An error that already names a class, such as an error from a
     * nested object, keeps it.
     *
     * @internal
     */
    public function whileCreating(string $class): self
    {
        if (!$this->named) {
            $this->named = true;
            $this->message = sprintf('Could not create %s from the JSON object: %s', $class, $this->getMessage());
        }
        return $this;
    }
}
