<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

/**
 * Sets the "minProperties" keyword in the generated JSON Schema.
 *
 * Can be applied to a property to annotate a single field, or to a class to annotate the schema of the entire class
 * (useful for map types that implement JsonSerializable).
 *
 * ```php
 * // On a property:
 * public function __construct(
 *     #[MinProperties(1)]
 *     /** @var array<string, int> {@*}
 *     public array $counts,
 * ) {}
 *
 * // On a class:
 * #[MinProperties(1)]
 * final readonly class NonEmptyMap implements JsonSerializable { ... }
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/object#size
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class MinProperties
{
    /**
     * @param int<0, max> $value
     */
    public function __construct(
        public int $value,
    ) {
    }
}
