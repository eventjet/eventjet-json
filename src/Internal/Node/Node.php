<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use JsonException;
use Stringable;

/**
 * A validated declaration, compiled once and applied to every decoded value. Its string form is the type that
 * errors report.
 *
 * @internal
 */
interface Node extends Stringable
{
    /**
     * Returns the PHP value for a decoded JSON value, or a DecodeError if the value does not match.
     *
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     */
    public function decode(mixed $value, string $path): mixed;

    /**
     * Returns the classes that decoded values may be instances of, including declared classes that are not loaded.
     *
     * @return list<string>
     */
    public function dependencies(): array;

    /**
     * Returns the JSON value types for which a union selects this node: their get_debug_type(), or true or false for
     * booleans. Empty for nodes that are not union members, and for enums, which unions group by backing type.
     *
     * @return list<string>
     */
    public function valueTypes(): array;
}
