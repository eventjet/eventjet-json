<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
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
     * Returns the PHP value for a decoded JSON value.
     *
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws DecodeError If the value does not match.
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
     * Returns the JSON values this node claims as a union member, by JSON value type: their get_debug_type(), or true or
     * false for booleans. For each type, `every` tells whether the node takes every value that it doesn't list, and
     * `values` lists JSON values with the PHP values the node returns for them, keyed by var_export() of the JSON value.
     * Empty for nodes that are not union members.
     *
     * @return array<string, array{every: bool, values: array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>}>
     */
    public function claims(): array;
}
