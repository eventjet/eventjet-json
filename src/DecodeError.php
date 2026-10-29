<?php

declare(strict_types=1);

namespace Eventjet\Json;

use RuntimeException;
use Throwable;

/** @api */
final class DecodeError extends RuntimeException
{
    /** @internal */
    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
