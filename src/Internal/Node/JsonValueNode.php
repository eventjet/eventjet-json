<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\JsonValue;
use InvalidArgumentException;
use Override;

/**
 * Wraps any JSON value, null included, in a JsonValue. A field declared as `JsonValue|null` stays null only if its
 * member is absent, so it decodes with this node too.
 *
 * @internal
 */
final readonly class JsonValueNode implements Node
{
    /** @param bool $nullable Whether the field is declared as `JsonValue|null`. */
    public function __construct(
        public bool $nullable,
    ) {}

    /** JSON numbers too large for a float decode as INF, which a JsonValue rejects. */
    #[Override]
    public function decode(mixed $value, string $path): JsonValue
    {
        try {
            return new JsonValue($value);
        } catch (InvalidArgumentException $error) {
            throw InvalidValue::jsonValue($path, $error);
        }
    }

    /** UnionBuilder rejects every union with a JsonValue member, so it never asks for these claims. */
    #[Override]
    public function claims(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return JsonValue::class;
    }
}
