<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;
use InvalidArgumentException;

use function str_starts_with;

/**
 * Names the JSON member that identifies the class of each object in a union of several classes, for a field where more
 * than one member could. The decoder still works out which value selects which class.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Discriminator
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
