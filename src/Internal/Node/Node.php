<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use JsonException;

/**
 * A validated declaration, compiled once and applied to every decoded value.
 *
 * @internal
 */
interface Node
{
    /**
     * Returns the PHP value for a decoded JSON value, or a DecodeError if the value does not match.
     *
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     */
    public function decode(mixed $value, string $path): mixed;

    /** @return list<class-string> */
    public function dependencies(): array;
}
