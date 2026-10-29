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
 * As a union member, a node claims JSON values by JSON value type. For each type, `values` lists JSON values with the
 * PHP values the node returns for them, keyed by var_export() of the JSON value. `others` tells what the node does with
 * the other values of the type: it accepts them, widens them (returns integers as floats, unless another member accepts
 * them), rejects them (takes them so that no other member can, but returns no value for them), or leaves them to other
 * members (null). `enums` are the backed enums whose cases the node returns.
 *
 * @psalm-type ClaimValues = array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>
 * @psalm-type Claim = array{others: 'accepts'|'widens'|'rejects'|null, enums: list<class-string<BackedEnum>>, values: ClaimValues}
 * @psalm-type Claims = array<string, Claim>
 * @phpstan-type ClaimValues array<array-key, array{bool|float|int|string, bool|float|int|string|BackedEnum}>
 * @phpstan-type Claim array{others: 'accepts'|'widens'|'rejects'|null, enums: list<class-string<BackedEnum>>, values: ClaimValues}
 * @phpstan-type Claims array<string, Claim>
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
     * Returns what this node claims as a union member, keyed by JSON value type: the get_debug_type() of the JSON value,
     * or true or false for booleans. Empty for nodes that are not union members.
     *
     * @return Claims
     */
    public function claims(): array;
}
