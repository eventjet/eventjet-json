<?php

declare(strict_types=1);

namespace Eventjet\Json;

use RuntimeException;
use Throwable;

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
        parent::__construct($message, previous: $previous);
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
