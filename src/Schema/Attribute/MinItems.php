<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

/**
 * Sets the "minItems" keyword in the generated JSON Schema.
 *
 * Can be applied to a property to annotate a single field, or to a class to annotate the schema of the entire class
 * (useful for list types that implement JsonSerializable).
 *
 * ```php
 * // On a property:
 * public function __construct(
 *     #[MinItems(1)]
 *     /** @var list<string> {@*}
 *     public array $tags,
 * ) {}
 *
 * // On a class:
 * #[MinItems(1)]
 * final readonly class TagList implements JsonSerializable { ... }
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/array#minItems
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class MinItems
{
    /**
     * @param int<0, max> $value
     */
    public function __construct(
        public int $value,
    ) {
    }
}
