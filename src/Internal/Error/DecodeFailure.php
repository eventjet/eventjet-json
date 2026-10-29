<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use RuntimeException;
use Throwable;

use function preg_replace;
use function sprintf;

/**
 * Carries an error through the library until the public API turns it into a DecodeError, which then never changes.
 *
 * @internal
 */
final class DecodeFailure extends RuntimeException
{
    private string|null $class = null;

    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** Reports an error thrown by other code, such as a constructor, with its message and as the previous exception. */
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
     */
    public function whileCreating(string $class): self
    {
        $this->class ??= $class;
        return $this;
    }

    public function toError(): DecodeError
    {
        $message = $this->class === null
            ? $this->getMessage()
            : sprintf('Could not create %s from the JSON object: %s', $this->class, $this->getMessage());
        return new DecodeError($this->kind, $message, $this->getPrevious());
    }
}
