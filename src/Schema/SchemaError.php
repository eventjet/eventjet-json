<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use RuntimeException;
use Throwable;

/**
 * Why a schema can't be compiled, such as a reference that can't be resolved or a regex that isn't valid ECMA-262.
 *
 * @api
 */
final class SchemaError extends RuntimeException
{
    /** @internal */
    public function __construct(string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
