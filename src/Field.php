<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;
use InvalidArgumentException;

use function str_starts_with;

/** @api */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /** @throws InvalidArgumentException */
    public function __construct(
        public string $name,
    ) {
        if (str_starts_with($name, "\0")) {
            throw new InvalidArgumentException(
                'JSON field names must not start with a null byte because PHP treats them as non-public object properties.',
            );
        }
    }
}
